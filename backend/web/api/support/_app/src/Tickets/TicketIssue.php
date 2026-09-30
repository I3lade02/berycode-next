<?php

declare(strict_types=1);

namespace BeryCode\Support\Tickets;

/** Stored issue of a ticket, as rendered to Slack. */
final class TicketIssue
{
    public function __construct(
        public readonly int $id,
        public readonly int $position,
        public readonly RequestType $requestType,
        public readonly Priority $priority,
        public readonly string $subject,
        public readonly string $description,
        public readonly ?string $slackReplyTs = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['position'],
            RequestType::from((string) $row['request_type']),
            Priority::from((string) $row['priority']),
            (string) $row['subject'],
            (string) $row['description'],
            $row['slack_reply_ts'] === null ? null : (string) $row['slack_reply_ts'],
        );
    }
}
