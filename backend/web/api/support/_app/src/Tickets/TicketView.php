<?php

declare(strict_types=1);

namespace BeryCode\Support\Tickets;

use BeryCode\Support\Time;

/** Read model of a ticket joined with its project and issues, used for Slack rendering and delivery. */
final class TicketView
{
    /** @param non-empty-list<TicketIssue> $issues ordered by position */
    public function __construct(
        public readonly int $id,
        public readonly string $reference,
        public readonly string $projectCode,
        public readonly string $projectName,
        public readonly string $customerName,
        public readonly string $customerEmail,
        public readonly string $subject,
        public readonly array $issues,
        public readonly Priority $priority,
        public readonly TicketStatus $status,
        public readonly ?string $assignee,
        public readonly string $locale,
        public readonly \DateTimeImmutable $createdAt,
        public readonly int $version,
        public readonly string $slackChannelId,
        public readonly ?string $slackMessageChannelId,
        public readonly ?string $slackMessageTs,
        public readonly string $deliveryState,
        public readonly int $deliveryAttempts,
        public readonly int $syncAttempts,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     * @param non-empty-list<TicketIssue> $issues
     */
    public static function fromRow(array $row, array $issues): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['reference'],
            (string) $row['project_code'],
            (string) $row['project_name'],
            (string) $row['customer_name'],
            (string) $row['customer_email'],
            (string) $row['subject'],
            $issues,
            Priority::from((string) $row['priority']),
            TicketStatus::from((string) $row['status']),
            $row['assignee_slack_user_id'] === null ? null : (string) $row['assignee_slack_user_id'],
            (string) $row['locale'],
            Time::fromDb((string) $row['created_at']) ?? new \DateTimeImmutable('@0'),
            (int) $row['version'],
            (string) $row['slack_channel_id'],
            $row['slack_message_channel_id'] === null ? null : (string) $row['slack_message_channel_id'],
            $row['slack_message_ts'] === null ? null : (string) $row['slack_message_ts'],
            (string) $row['delivery_state'],
            (int) $row['delivery_attempts'],
            (int) $row['sync_attempts'],
        );
    }

    /** Multi-issue tickets carry each issue's details as a reply in the message thread. */
    public function usesThread(): bool
    {
        return count($this->issues) > 1;
    }
}
