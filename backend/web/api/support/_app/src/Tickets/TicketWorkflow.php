<?php

declare(strict_types=1);

namespace BeryCode\Support\Tickets;

/**
 * Allowed staff transitions. Pure and deterministic so repeated or concurrent
 * clicks resolve consistently once rows are locked:
 *
 *   Assign to me: any status; no-op when already assigned to the actor.
 *   Start work:   NEW -> IN_PROGRESS (also assigns the actor if unassigned);
 *                 no-op when IN_PROGRESS; rejected when RESOLVED (reopen first).
 *   Resolve:      NEW | IN_PROGRESS -> RESOLVED; no-op when RESOLVED.
 *   Reopen:       RESOLVED -> NEW; no-op when already open.
 */
final class TicketWorkflow
{
    public static function apply(TicketStatus $status, ?string $assignee, StaffAction $action, string $actor): TransitionResult
    {
        switch ($action) {
            case StaffAction::ASSIGN_TO_ME:
                return $assignee === $actor
                    ? TransitionResult::noop('already_assigned_to_you', $status, $assignee)
                    : TransitionResult::changed($status, $actor);

            case StaffAction::START:
                if ($status === TicketStatus::RESOLVED) {
                    return TransitionResult::invalid('resolved_reopen_first', $status, $assignee);
                }

                if ($status === TicketStatus::IN_PROGRESS) {
                    return TransitionResult::noop('already_in_progress', $status, $assignee);
                }

                return TransitionResult::changed(TicketStatus::IN_PROGRESS, $assignee ?? $actor);

            case StaffAction::RESOLVE:
                return $status === TicketStatus::RESOLVED
                    ? TransitionResult::noop('already_resolved', $status, $assignee)
                    : TransitionResult::changed(TicketStatus::RESOLVED, $assignee);

            case StaffAction::REOPEN:
                return $status === TicketStatus::RESOLVED
                    ? TransitionResult::changed(TicketStatus::NEW, $assignee)
                    : TransitionResult::noop('already_open', $status, $assignee);
        }
    }
}
