<?php

declare(strict_types=1);

namespace BeryCode\Support\Tickets;

/** A verified, authorized Slack button click (values still untrusted until checked against the DB). */
final class StaffActionRequest
{
    public function __construct(
        public readonly int $ticketId,
        public readonly StaffAction $action,
        public readonly string $actorUserId,
        public readonly string $channelId,
        public readonly string $messageTs,
        public readonly string $dedupeKey,
    ) {
    }
}
