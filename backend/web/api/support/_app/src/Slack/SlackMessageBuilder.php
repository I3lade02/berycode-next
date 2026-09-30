<?php

declare(strict_types=1);

namespace BeryCode\Support\Slack;

use BeryCode\Support\Text;
use BeryCode\Support\Tickets\Priority;
use BeryCode\Support\Tickets\StaffAction;
use BeryCode\Support\Tickets\TicketIssue;
use BeryCode\Support\Tickets\TicketStatus;
use BeryCode\Support\Tickets\TicketView;

/**
 * Renders a ticket as a Block Kit message. The database is authoritative, so the
 * same builder produces the initial post and every chat.update.
 *
 * Safety: customer-supplied text (name, email, subject, description) is only
 * ever placed in plain_text objects, which Slack never parses for mentions,
 * links or formatting. The fallback `text` escapes &, < and > as Slack requires.
 *
 * Limits (https://docs.slack.dev/reference/block-kit/blocks): header 150 chars,
 * section text 3000, field 2000, max 10 fields, max 50 blocks.
 */
final class SlackMessageBuilder
{
    public const ACTIONS_BLOCK_ID = 'support_ticket_actions';
    public const METADATA_EVENT_TYPE = 'berycode_support_ticket';
    private const HEADER_MAX = 150;
    private const SECTION_TEXT_MAX = 3000;
    private const DESCRIPTION_CHUNK = 2900;
    private const FIELD_MAX = 2000;
    private const FALLBACK_MAX = 3000;
    private const PREVIEW_MAX = 280;

    private SlackLabels $labels;

    public function __construct(string $locale)
    {
        $this->labels = new SlackLabels($locale);
    }

    public function labels(): SlackLabels
    {
        return $this->labels;
    }

    /**
     * The ticket message (thread parent) with the staff buttons. A single-issue
     * ticket shows the full description here; a multi-issue ticket shows an
     * overview and each issue's details go to the thread (buildIssueReply()).
     *
     * @return array{text: string, blocks: list<array<string, mixed>>, metadata: array<string, mixed>}
     */
    public function build(TicketView $ticket): array
    {
        $l = $this->labels;
        $blocks = [];
        $issueCount = count($ticket->issues);
        $title = $ticket->reference . ' · ' . $ticket->subject;

        if ($ticket->usesThread()) {
            $more = $issueCount - 1;
            $title = Text::truncate($title, self::HEADER_MAX - 20) . ' ' . $l->get($more <= 4 ? 'header_more' : 'header_more_many', (string) $more);
        }

        $blocks[] = ['type' => 'header', 'text' => self::plain(Text::truncate($title, self::HEADER_MAX))];

        $statusEmoji = match ($ticket->status) {
            TicketStatus::NEW => ':new:',
            TicketStatus::IN_PROGRESS => ':hammer_and_wrench:',
            TicketStatus::RESOLVED => ':white_check_mark:',
        };

        $blocks[] = [
            'type' => 'section',
            'fields' => [
                self::mrkdwn('*' . $l->get('project') . "*\n" . self::escape(Text::truncate($ticket->projectName, 1000)) . ' (`' . self::escape($ticket->projectCode) . '`)'),
                self::mrkdwn('*' . $l->get('status') . "*\n" . $statusEmoji . ' ' . $l->get('status_' . $ticket->status->value)),
                $ticket->usesThread()
                    ? self::mrkdwn('*' . $l->get('issue_count') . "*\n" . $issueCount)
                    : self::mrkdwn('*' . $l->get('type') . "*\n" . $l->get('type_' . $ticket->issues[0]->requestType->value)),
                self::mrkdwn('*' . $l->get('priority') . "*\n" . $this->priority($ticket->priority, true)),
                self::mrkdwn('*' . $l->get('assignee') . "*\n" . $this->assignee($ticket->assignee)),
                self::mrkdwn('*' . $l->get('customer_language') . "*\n" . $l->get('language_' . $ticket->locale)),
            ],
        ];

        $blocks[] = [
            'type' => 'section',
            'fields' => [
                self::plain(Text::truncate($l->get('customer') . ': ' . $ticket->customerName, self::FIELD_MAX)),
                self::plain(Text::truncate($l->get('email') . ': ' . $ticket->customerEmail, self::FIELD_MAX)),
            ],
        ];

        $blocks[] = ['type' => 'divider'];

        if ($ticket->usesThread()) {
            $blocks[] = ['type' => 'section', 'text' => self::mrkdwn($l->get('issues_heading', (string) $issueCount))];

            foreach ($ticket->issues as $issue) {
                $preview = Text::truncate(Text::singleLine($issue->description), self::PREVIEW_MAX);
                $blocks[] = ['type' => 'section', 'text' => self::plain($issue->position . '. ' . $issue->subject . "\n" . $preview)];
                $blocks[] = ['type' => 'context', 'elements' => [self::mrkdwn($this->issueMeta($issue))]];
            }
        } else {
            $blocks[] = ['type' => 'section', 'text' => self::mrkdwn('*' . $l->get('description') . '*')];

            foreach (Text::chunk($ticket->issues[0]->description, self::DESCRIPTION_CHUNK) as $chunk) {
                $blocks[] = ['type' => 'section', 'text' => self::plain(Text::truncate($chunk, self::SECTION_TEXT_MAX))];
            }
        }

        $created = $ticket->createdAt->getTimestamp();
        $blocks[] = [
            'type' => 'context',
            'elements' => [
                self::mrkdwn(sprintf(
                    '%s <!date^%d^{date_short_pretty} {time}|%s> · %s',
                    $l->get('received'),
                    $created,
                    gmdate('Y-m-d H:i', $created) . ' UTC',
                    $l->get('reply_hint'),
                )),
            ],
        ];

        $actions = $this->buttons($ticket);

        if ($actions !== []) {
            $blocks[] = ['type' => 'actions', 'block_id' => self::ACTIONS_BLOCK_ID, 'elements' => $actions];
        }

        $fallback = sprintf(
            '%s · %s: %s (%s)',
            $ticket->reference,
            $ticket->projectName,
            $ticket->subject,
            $l->get('status_' . $ticket->status->value),
        );

        return [
            'text' => self::escape(Text::truncate($fallback, self::FALLBACK_MAX)),
            'blocks' => $blocks,
            'metadata' => [
                'event_type' => self::METADATA_EVENT_TYPE,
                'event_payload' => ['ticket_id' => $ticket->id, 'reference' => $ticket->reference, 'issue_count' => $issueCount],
            ],
        ];
    }

    /**
     * Thread reply with one issue's full details (multi-issue tickets). These
     * replies are never updated; status lives on the parent message.
     *
     * @return array{text: string, blocks: list<array<string, mixed>>, metadata: array<string, mixed>}
     */
    public function buildIssueReply(TicketView $ticket, TicketIssue $issue): array
    {
        $count = count($ticket->issues);
        $blocks = [
            ['type' => 'header', 'text' => self::plain(Text::truncate($issue->position . '/' . $count . ' · ' . $issue->subject, self::HEADER_MAX))],
            ['type' => 'context', 'elements' => [self::mrkdwn($this->issueMeta($issue) . ' · ' . self::escape($ticket->reference))]],
        ];

        foreach (Text::chunk($issue->description, self::DESCRIPTION_CHUNK) as $chunk) {
            $blocks[] = ['type' => 'section', 'text' => self::plain(Text::truncate($chunk, self::SECTION_TEXT_MAX))];
        }

        return [
            'text' => self::escape(Text::truncate(
                $ticket->reference . ' · ' . $this->labels->get('reply_issue_of', (string) $issue->position, (string) $count) . ': ' . $issue->subject,
                self::FALLBACK_MAX,
            )),
            'blocks' => $blocks,
            'metadata' => [
                'event_type' => self::METADATA_EVENT_TYPE . '_issue',
                'event_payload' => ['ticket_id' => $ticket->id, 'reference' => $ticket->reference, 'issue_position' => $issue->position],
            ],
        ];
    }

    private function issueMeta(TicketIssue $issue): string
    {
        return $this->labels->get('type_' . $issue->requestType->value) . ' · ' . $this->priority($issue->priority, false);
    }

    private function priority(Priority $priority, bool $bare): string
    {
        $label = $this->labels->get('priority_' . $priority->value);

        if (!$bare) {
            $label = $this->labels->get('priority_inline', $label);
        }

        return $priority === Priority::HIGH ? ':red_circle: *' . $label . '*' : $label;
    }

    /** Escapes the three characters Slack treats as control sequences in mrkdwn/text. */
    public static function escape(string $text): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
    }

    /** @return array{type: string, text: string, emoji: bool} */
    private static function plain(string $text): array
    {
        return ['type' => 'plain_text', 'text' => $text === '' ? ' ' : $text, 'emoji' => false];
    }

    /** @return array{type: string, text: string} */
    private static function mrkdwn(string $text): array
    {
        return ['type' => 'mrkdwn', 'text' => $text];
    }

    private function assignee(?string $userId): string
    {
        if ($userId === null || !preg_match('/^[UW][A-Z0-9]{2,31}$/', $userId)) {
            return '_' . $this->labels->get('unassigned') . '_';
        }

        return '<@' . $userId . '>';
    }

    /** @return list<array<string, mixed>> */
    private function buttons(TicketView $ticket): array
    {
        $value = (string) $ticket->id;
        $button = fn (StaffAction $action, string $label, ?string $style = null): array => array_filter([
            'type' => 'button',
            'action_id' => $action->value,
            'text' => self::plain($this->labels->get($label)),
            'value' => $value,
            'style' => $style,
        ], static fn ($item): bool => $item !== null);

        return match ($ticket->status) {
            TicketStatus::NEW => [
                $button(StaffAction::ASSIGN_TO_ME, 'button_assign'),
                $button(StaffAction::START, 'button_start', 'primary'),
                $button(StaffAction::RESOLVE, 'button_resolve'),
            ],
            TicketStatus::IN_PROGRESS => [
                $button(StaffAction::ASSIGN_TO_ME, 'button_assign'),
                $button(StaffAction::RESOLVE, 'button_resolve', 'primary'),
            ],
            TicketStatus::RESOLVED => [
                $button(StaffAction::REOPEN, 'button_reopen'),
            ],
        };
    }
}
