<?php

declare(strict_types=1);

namespace BeryCode\Support\Tests\Support;

use BeryCode\Support\Clock;

final class FixedClock implements Clock
{
    private \DateTimeImmutable $now;

    public function __construct(string $now = '2026-01-15 10:00:00')
    {
        $this->now = new \DateTimeImmutable($now, new \DateTimeZone('UTC'));
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify(sprintf('%+d seconds', $seconds));
    }
}
