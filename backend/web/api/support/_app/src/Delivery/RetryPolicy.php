<?php

declare(strict_types=1);

namespace BeryCode\Support\Delivery;

/**
 * Bounded exponential-ish backoff. With the defaults a ticket is retried for
 * roughly 16 hours before it is marked FAILED for a human to look at.
 */
final class RetryPolicy
{
    /** @param list<int> $delaysSeconds delay after the Nth failed attempt */
    public function __construct(
        public readonly int $maxAttempts = 10,
        private array $delaysSeconds = [60, 120, 300, 900, 1800, 3600, 7200, 14400, 28800],
    ) {
    }

    public function exhausted(int $attemptsSoFar): bool
    {
        return $attemptsSoFar >= $this->maxAttempts;
    }

    /**
     * When Slack sends Retry-After (rate limiting) we wait exactly that long, but
     * never less than the local schedule's delay capped at one minute.
     */
    public function delayAfter(int $attemptsSoFar, ?int $retryAfterSeconds): int
    {
        $index = max(0, min(count($this->delaysSeconds) - 1, $attemptsSoFar - 1));
        $delay = $this->delaysSeconds[$index];

        return $retryAfterSeconds === null ? $delay : max($retryAfterSeconds, min($delay, 60));
    }
}
