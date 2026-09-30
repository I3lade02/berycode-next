<?php

declare(strict_types=1);

namespace BeryCode\Support\Security;

use BeryCode\Support\Clock;
use BeryCode\Support\Time;
use PDO;

/**
 * Fixed-window counters stored in MySQL, so the limit is shared by every PHP
 * worker on the host. Subjects (IPs, emails) are HMAC-hashed with a server
 * secret before they are stored.
 */
final class RateLimiter
{
    public function __construct(private PDO $pdo, private Clock $clock, private string $secret)
    {
    }

    public function hit(string $scope, string $subject, int $limit, int $windowSeconds): RateLimitDecision
    {
        $now = $this->clock->now()->getTimestamp();
        $windowStart = intdiv($now, $windowSeconds) * $windowSeconds;
        $expiresAt = $windowStart + $windowSeconds;
        $key = hash_hmac('sha256', $scope . '|' . $subject . '|' . $windowStart, $this->secret);

        $this->pdo->prepare(
            'INSERT INTO support_rate_limits (bucket_key, hits, expires_at) VALUES (?, 1, ?)
             ON DUPLICATE KEY UPDATE hits = hits + 1',
        )->execute([$key, Time::db(new \DateTimeImmutable('@' . $expiresAt))]);

        $select = $this->pdo->prepare('SELECT hits FROM support_rate_limits WHERE bucket_key = ?');
        $select->execute([$key]);
        $hits = (int) $select->fetchColumn();

        return new RateLimitDecision($hits <= $limit, max(1, $expiresAt - $now));
    }

    public function purgeExpired(): int
    {
        $statement = $this->pdo->prepare('DELETE FROM support_rate_limits WHERE expires_at < ? LIMIT 5000');
        $statement->execute([Time::db($this->clock->now())]);

        return $statement->rowCount();
    }
}
