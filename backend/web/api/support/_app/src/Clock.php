<?php

declare(strict_types=1);

namespace BeryCode\Support;

/**
 * Source of "now" in UTC. All timestamps are computed in PHP and passed to SQL
 * as parameters so time is consistent and testable.
 */
interface Clock
{
    public function now(): \DateTimeImmutable;
}
