<?php

declare(strict_types=1);

namespace BeryCode\Support\Tickets;

use BeryCode\Support\Clock;
use BeryCode\Support\Database\Database;
use BeryCode\Support\Logger;
use BeryCode\Support\Projects\Project;
use BeryCode\Support\Time;
use PDO;
use PDOException;

final class TicketService
{
    public function __construct(
        private PDO $pdo,
        private TicketRepository $tickets,
        private Clock $clock,
        private Logger $logger,
        private string $referencePrefix,
    ) {
    }

    /**
     * Returns the earlier ticket for a retried submission, or null when the key
     * is new.
     *
     * @throws IdempotencyConflict when the key was used for different content
     */
    public function findExisting(TicketSubmission $submission): ?CreatedTicket
    {
        $existing = $this->tickets->findByIdempotencyKey($submission->idempotencyKey);

        if ($existing === null) {
            return null;
        }

        if (!hash_equals($existing['submission_fingerprint'], $submission->fingerprint())) {
            throw new IdempotencyConflict();
        }

        return new CreatedTicket($existing['id'], $existing['reference'], true);
    }

    /**
     * Saves the ticket, its CREATED event and its pending Slack delivery in one
     * transaction. Once this returns, the request is durably received.
     *
     * @throws IdempotencyConflict
     */
    public function create(TicketSubmission $submission, Project $project): CreatedTicket
    {
        $now = Time::db($this->clock->now());

        try {
            return Database::transaction($this->pdo, function () use ($submission, $project, $now): CreatedTicket {
                $id = $this->tickets->insert($submission, $project, $now);
                $reference = sprintf('%s-%06d', $this->referencePrefix, $id);
                $this->tickets->setReference($id, $reference);
                $this->tickets->addEvent($id, 'CREATED', TicketRepository::ACTOR_CUSTOMER, null, $now, [
                    'to_status' => TicketStatus::NEW->value,
                ]);

                return new CreatedTicket($id, $reference, false);
            });
        } catch (PDOException $exception) {
            // A concurrent retry of the same submission won the insert race.
            if (Database::isDuplicateKey($exception, 'uq_support_tickets_idempotency')) {
                $existing = $this->findExisting($submission);

                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $exception;
        }
    }

    /**
     * Applies a staff action under a row lock. The interaction record, the
     * status/assignment change and the history events commit together, so a
     * replayed payload or a concurrent click can never apply twice or interleave.
     */
    public function applyStaffAction(StaffActionRequest $request): StaffActionResult
    {
        $now = Time::db($this->clock->now());

        $result = Database::transaction($this->pdo, function () use ($request, $now): StaffActionResult {
            if (!$this->tickets->recordInteraction($request->dedupeKey, $request->ticketId, $request->actorUserId, $request->action->value, $now)) {
                return new StaffActionResult(StaffActionResult::DUPLICATE);
            }

            $ticket = $this->tickets->lock($request->ticketId);

            if ($ticket === null) {
                $this->tickets->setInteractionOutcome($request->dedupeKey, StaffActionResult::NOT_FOUND);

                return new StaffActionResult(StaffActionResult::NOT_FOUND);
            }

            // The click must come from the ticket's own recorded message (thread
            // replies for multi-issue tickets may still be pending; that is fine).
            if (
                $ticket->slackMessageTs === null
                || $ticket->slackMessageChannelId !== $request->channelId
                || $ticket->slackMessageTs !== $request->messageTs
            ) {
                $this->tickets->setInteractionOutcome($request->dedupeKey, StaffActionResult::MESSAGE_MISMATCH);

                return new StaffActionResult(StaffActionResult::MESSAGE_MISMATCH, null, $ticket->reference);
            }

            $transition = TicketWorkflow::apply($ticket->status, $ticket->assignee, $request->action, $request->actorUserId);

            if ($transition->outcome !== TransitionResult::CHANGED) {
                $outcome = $transition->outcome === TransitionResult::NOOP ? StaffActionResult::NOOP : StaffActionResult::INVALID;
                $this->tickets->setInteractionOutcome($request->dedupeKey, $outcome);

                return new StaffActionResult($outcome, $transition->reason, $ticket->reference);
            }

            $this->tickets->applyStaffChange($ticket->id, $transition->status, $transition->assignee, $now);

            if ($transition->assignee !== $ticket->assignee) {
                $this->tickets->addEvent($ticket->id, 'ASSIGNED', TicketRepository::ACTOR_SLACK_USER, $request->actorUserId, $now, [
                    'from_assignee' => $ticket->assignee,
                    'to_assignee' => $transition->assignee,
                ]);
            }

            if ($transition->status !== $ticket->status) {
                $this->tickets->addEvent($ticket->id, 'STATUS_CHANGED', TicketRepository::ACTOR_SLACK_USER, $request->actorUserId, $now, [
                    'from_status' => $ticket->status->value,
                    'to_status' => $transition->status->value,
                ]);
            }

            $this->tickets->setInteractionOutcome($request->dedupeKey, StaffActionResult::CHANGED);

            return new StaffActionResult(StaffActionResult::CHANGED, null, $ticket->reference);
        });

        $this->logger->info('staff_action', [
            'ticket_id' => $request->ticketId,
            'action' => $request->action->value,
            'actor' => $request->actorUserId,
            'outcome' => $result->outcome,
            'reason' => $result->reason,
        ]);

        return $result;
    }
}
