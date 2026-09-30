<?php

declare(strict_types=1);

namespace BeryCode\Support\Security;

final class RateLimitDecision
{
    public function __construct(
        public readonly bool $allowed,
        public readonly int $retryAfterSeconds,
    ) {
    }
}
