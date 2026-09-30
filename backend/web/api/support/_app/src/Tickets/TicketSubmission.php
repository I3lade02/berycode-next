<?php

declare(strict_types=1);

namespace BeryCode\Support\Tickets;

/** A validated, normalized customer request with one or more issues. */
final class TicketSubmission
{
    /** @param non-empty-list<IssueSubmission> $issues */
    public function __construct(
        public readonly string $customerName,
        public readonly string $customerEmail,
        public readonly string $projectInput,
        public readonly array $issues,
        public readonly string $locale,
        public readonly string $idempotencyKey,
    ) {
    }

    /** Ticket title: the first issue's subject. */
    public function subject(): string
    {
        return $this->issues[0]->subject;
    }

    /** The ticket is HIGH priority when any of its issues is. */
    public function priority(): Priority
    {
        foreach ($this->issues as $issue) {
            if ($issue->priority === Priority::HIGH) {
                return Priority::HIGH;
            }
        }

        return Priority::NORMAL;
    }

    /**
     * Hash of the submitted content, used to detect an idempotency key being
     * reused for a different request.
     */
    public function fingerprint(): string
    {
        return hash('sha256', (string) json_encode([
            $this->customerName,
            strtolower($this->customerEmail),
            mb_strtolower($this->projectInput, 'UTF-8'),
            array_map(static fn (IssueSubmission $issue): array => [
                $issue->requestType->value,
                $issue->priority->value,
                $issue->subject,
                $issue->description,
            ], $this->issues),
        ], JSON_UNESCAPED_UNICODE));
    }
}
