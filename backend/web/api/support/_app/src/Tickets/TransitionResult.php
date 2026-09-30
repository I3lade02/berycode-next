<?php

declare(strict_types=1);

namespace BeryCode\Support\Tickets;

final class TransitionResult
{
    public const CHANGED = 'changed';
    public const NOOP = 'noop';
    public const INVALID = 'invalid';

    private function __construct(
        public readonly string $outcome,
        public readonly ?string $reason,
        public readonly TicketStatus $status,
        public readonly ?string $assignee,
    ) {
    }

    public static function changed(TicketStatus $status, ?string $assignee): self
    {
        return new self(self::CHANGED, null, $status, $assignee);
    }

    public static function noop(string $reason, TicketStatus $status, ?string $assignee): self
    {
        return new self(self::NOOP, $reason, $status, $assignee);
    }

    public static function invalid(string $reason, TicketStatus $status, ?string $assignee): self
    {
        return new self(self::INVALID, $reason, $status, $assignee);
    }
}
