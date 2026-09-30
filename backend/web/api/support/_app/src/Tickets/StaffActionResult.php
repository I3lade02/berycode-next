<?php

declare(strict_types=1);

namespace BeryCode\Support\Tickets;

final class StaffActionResult
{
    public const CHANGED = 'changed';
    public const NOOP = 'noop';
    public const INVALID = 'invalid';
    public const DUPLICATE = 'duplicate';
    public const MESSAGE_MISMATCH = 'message_mismatch';
    public const NOT_FOUND = 'not_found';

    public function __construct(
        public readonly string $outcome,
        public readonly ?string $reason = null,
        public readonly ?string $reference = null,
    ) {
    }

    public function changed(): bool
    {
        return $this->outcome === self::CHANGED;
    }
}
