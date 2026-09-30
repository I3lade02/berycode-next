<?php

declare(strict_types=1);

namespace BeryCode\Support\Tickets;

use BeryCode\Support\Database\Database;
use BeryCode\Support\Projects\Project;
use PDO;

/**
 * All SQL for tickets, events and Slack interactions. Every statement is
 * parameterized; the only interpolated values are integers.
 */
final class TicketRepository
{
    public const ACTOR_CUSTOMER = 'CUSTOMER';
    public const ACTOR_SLACK_USER = 'SLACK_USER';
    public const ACTOR_SYSTEM = 'SYSTEM';

    public function __construct(private PDO $pdo)
    {
    }

    /** @return array{id: int, reference: string, submission_fingerprint: string}|null */
    public function findByIdempotencyKey(string $key): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, reference, submission_fingerprint FROM support_tickets WHERE idempotency_key = ?');
        $statement->execute([$key]);
        $row = $statement->fetch();

        return $row === false ? null : [
            'id' => (int) $row['id'],
            'reference' => (string) $row['reference'],
            'submission_fingerprint' => (string) $row['submission_fingerprint'],
        ];
    }

    public function insert(TicketSubmission $submission, Project $project, string $now): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO support_tickets (
                project_id, customer_name, customer_email, subject, priority, locale,
                status, idempotency_key, submission_fingerprint, created_at, updated_at,
                slack_channel_id, delivery_state, delivery_next_attempt_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $statement->execute([
            $project->id,
            $submission->customerName,
            $submission->customerEmail,
            $submission->subject(),
            $submission->priority()->value,
            $submission->locale,
            TicketStatus::NEW->value,
            $submission->idempotencyKey,
            $submission->fingerprint(),
            $now,
            $now,
            $project->slackChannelId,
            'PENDING',
            $now,
        ]);

        $ticketId = (int) $this->pdo->lastInsertId();
        $insertIssue = $this->pdo->prepare(
            'INSERT INTO support_ticket_issues (ticket_id, position, request_type, priority, subject, description, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
        );

        foreach ($submission->issues as $index => $issue) {
            $insertIssue->execute([
                $ticketId,
                $index + 1,
                $issue->requestType->value,
                $issue->priority->value,
                $issue->subject,
                $issue->description,
                $now,
            ]);
        }

        return $ticketId;
    }

    public function setReference(int $id, string $reference): void
    {
        $this->pdo->prepare('UPDATE support_tickets SET reference = ? WHERE id = ?')->execute([$reference, $id]);
    }

    /**
     * @param array{from_status?: ?string, to_status?: ?string, from_assignee?: ?string, to_assignee?: ?string} $changes
     */
    public function addEvent(
        int $ticketId,
        string $action,
        string $actorType,
        ?string $actorId,
        string $now,
        array $changes = [],
        ?string $detail = null,
    ): void {
        $this->pdo->prepare(
            'INSERT INTO support_ticket_events
                (ticket_id, action, actor_type, actor_id, from_status, to_status, from_assignee, to_assignee, detail, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        )->execute([
            $ticketId,
            $action,
            $actorType,
            $actorId,
            $changes['from_status'] ?? null,
            $changes['to_status'] ?? null,
            $changes['from_assignee'] ?? null,
            $changes['to_assignee'] ?? null,
            $detail === null ? null : mb_substr($detail, 0, 255, 'UTF-8'),
            $now,
        ]);
    }

    public function view(int $id): ?TicketView
    {
        return $this->load($id, false);
    }

    /** Row-locks the ticket for the rest of the transaction. */
    public function lock(int $id): ?TicketView
    {
        return $this->load($id, true);
    }

    private function load(int $id, bool $forUpdate): ?TicketView
    {
        $statement = $this->pdo->prepare(
            'SELECT t.*, p.code AS project_code, p.display_name AS project_name
             FROM support_tickets t
             JOIN support_projects p ON p.id = t.project_id
             WHERE t.id = ?' . ($forUpdate ? ' FOR UPDATE' : ''),
        );
        $statement->execute([$id]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        $issues = $this->pdo->prepare('SELECT * FROM support_ticket_issues WHERE ticket_id = ? ORDER BY position');
        $issues->execute([$id]);
        $list = array_map([TicketIssue::class, 'fromRow'], $issues->fetchAll());

        if ($list === []) {
            throw new \RuntimeException('Ticket ' . $id . ' has no issues.');
        }

        return TicketView::fromRow($row, $list);
    }

    public function idByReference(string $reference): ?int
    {
        $statement = $this->pdo->prepare('SELECT id FROM support_tickets WHERE reference = ?');
        $statement->execute([strtoupper($reference)]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    // ---------------------------------------------------------------- delivery

    /**
     * Atomically claims the initial Slack delivery. Returns the state the ticket
     * was in (PENDING, or SENDING when an expired lease is taken over), or null
     * when another worker owns it or it is not due.
     */
    public function claimDelivery(int $id, string $now, string $leaseUntil): ?string
    {
        $select = $this->pdo->prepare('SELECT delivery_state FROM support_tickets WHERE id = ?');
        $select->execute([$id]);
        $previous = $select->fetchColumn();

        $claim = $this->pdo->prepare(
            "UPDATE support_tickets
             SET delivery_state = 'SENDING', delivery_attempts = delivery_attempts + 1,
                 delivery_lease_until = ?, delivery_last_attempt_at = ?
             WHERE id = ?
               AND ((delivery_state = 'PENDING' AND (delivery_next_attempt_at IS NULL OR delivery_next_attempt_at <= ?))
                 OR (delivery_state = 'SENDING' AND delivery_lease_until < ?))",
        );
        $claim->execute([$leaseUntil, $now, $id, $now, $now]);

        return $claim->rowCount() === 1 && is_string($previous) ? $previous : null;
    }

    /**
     * Records the posted ticket message (the thread parent). Staff buttons and
     * chat.update work from this point, even while thread replies are pending.
     */
    public function markParentPosted(int $id, string $channel, string $ts, int $renderedVersion, string $now): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE support_tickets
             SET slack_message_channel_id = ?, slack_message_ts = ?,
                 slack_synced_version = ?,
                 sync_state = IF(version > ?, 'PENDING', 'IDLE'),
                 sync_next_attempt_at = IF(version > ?, ?, NULL)
             WHERE id = ? AND delivery_state = 'SENDING' AND slack_message_ts IS NULL",
        );
        $statement->execute([$channel, $ts, $renderedVersion, $renderedVersion, $renderedVersion, $now, $id]);

        return $statement->rowCount() === 1;
    }

    public function markIssueReplyPosted(int $issueId, string $ts): void
    {
        $this->pdo->prepare('UPDATE support_ticket_issues SET slack_reply_ts = ? WHERE id = ? AND slack_reply_ts IS NULL')
            ->execute([$ts, $issueId]);
    }

    /** Everything (parent message and any thread replies) is in Slack. */
    public function markDelivered(int $id, string $now): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE support_tickets
             SET delivery_state = 'DELIVERED', delivered_at = ?,
                 delivery_lease_until = NULL, delivery_next_attempt_at = NULL, delivery_last_error = NULL
             WHERE id = ? AND delivery_state = 'SENDING'",
        );
        $statement->execute([$now, $id]);

        return $statement->rowCount() === 1;
    }

    public function scheduleDeliveryRetry(int $id, string $nextAttemptAt, string $error): void
    {
        $this->pdo->prepare(
            "UPDATE support_tickets
             SET delivery_state = 'PENDING', delivery_next_attempt_at = ?, delivery_lease_until = NULL, delivery_last_error = ?
             WHERE id = ? AND delivery_state = 'SENDING'",
        )->execute([$nextAttemptAt, $error, $id]);
    }

    public function markDeliveryFailed(int $id, string $error): void
    {
        $this->pdo->prepare(
            "UPDATE support_tickets
             SET delivery_state = 'FAILED', delivery_next_attempt_at = NULL, delivery_lease_until = NULL, delivery_last_error = ?
             WHERE id = ? AND delivery_state = 'SENDING'",
        )->execute([$error, $id]);
    }

    /** @return list<int> */
    public function dueDeliveryIds(string $now, int $limit): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id FROM support_tickets
             WHERE (delivery_state = 'PENDING' AND (delivery_next_attempt_at IS NULL OR delivery_next_attempt_at <= ?))
                OR (delivery_state = 'SENDING' AND delivery_lease_until < ?)
             ORDER BY id
             LIMIT " . max(1, $limit),
        );
        $statement->execute([$now, $now]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Puts FAILED deliveries (all, or one ticket) back in the queue. */
    public function requeueFailedDeliveries(?int $ticketId, string $now): int
    {
        $sql = "UPDATE support_tickets
                SET delivery_state = 'PENDING', delivery_attempts = 0, delivery_next_attempt_at = ?, delivery_last_error = NULL
                WHERE delivery_state = 'FAILED'";
        $params = [$now];

        if ($ticketId !== null) {
            $sql .= ' AND id = ?';
            $params[] = $ticketId;
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount();
    }

    // -------------------------------------------------------------------- sync

    public function claimSync(int $id, string $now, string $leaseUntil): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE support_tickets
             SET sync_lease_until = ?, sync_attempts = sync_attempts + 1
             WHERE id = ? AND slack_message_ts IS NOT NULL AND sync_state = 'PENDING'
               AND (sync_next_attempt_at IS NULL OR sync_next_attempt_at <= ?)
               AND (sync_lease_until IS NULL OR sync_lease_until < ?)",
        );
        $statement->execute([$leaseUntil, $id, $now, $now]);

        return $statement->rowCount() === 1;
    }

    /** Records a successful chat.update. Returns true when a newer version still needs syncing. */
    public function markSynced(int $id, int $renderedVersion, string $now): bool
    {
        $this->pdo->prepare(
            "UPDATE support_tickets
             SET slack_synced_version = GREATEST(slack_synced_version, ?),
                 sync_state = IF(version > ?, 'PENDING', 'IDLE'),
                 sync_next_attempt_at = IF(version > ?, ?, NULL),
                 sync_attempts = 0, sync_last_error = NULL, sync_lease_until = NULL
             WHERE id = ?",
        )->execute([$renderedVersion, $renderedVersion, $renderedVersion, $now, $id]);

        $statement = $this->pdo->prepare('SELECT sync_state FROM support_tickets WHERE id = ?');
        $statement->execute([$id]);

        return $statement->fetchColumn() === 'PENDING';
    }

    public function scheduleSyncRetry(int $id, string $nextAttemptAt, string $error): void
    {
        $this->pdo->prepare(
            'UPDATE support_tickets SET sync_next_attempt_at = ?, sync_last_error = ?, sync_lease_until = NULL WHERE id = ?',
        )->execute([$nextAttemptAt, $error, $id]);
    }

    public function markSyncFailed(int $id, string $error): void
    {
        $this->pdo->prepare(
            "UPDATE support_tickets
             SET sync_state = 'FAILED', sync_last_error = ?, sync_lease_until = NULL, sync_next_attempt_at = NULL
             WHERE id = ?",
        )->execute([$error, $id]);
    }

    /** @return list<int> */
    public function dueSyncIds(string $now, int $limit): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id FROM support_tickets
             WHERE sync_state = 'PENDING' AND slack_message_ts IS NOT NULL
               AND (sync_next_attempt_at IS NULL OR sync_next_attempt_at <= ?)
               AND (sync_lease_until IS NULL OR sync_lease_until < ?)
             ORDER BY id
             LIMIT " . max(1, $limit),
        );
        $statement->execute([$now, $now]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function requeueFailedSyncs(?int $ticketId, string $now): int
    {
        $sql = "UPDATE support_tickets
                SET sync_state = 'PENDING', sync_attempts = 0, sync_next_attempt_at = ?, sync_last_error = NULL
                WHERE sync_state = 'FAILED'";
        $params = [$now];

        if ($ticketId !== null) {
            $sql .= ' AND id = ?';
            $params[] = $ticketId;
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount();
    }

    // ----------------------------------------------------------- staff actions

    /** Returns false when this interaction was already recorded (a replay). */
    public function recordInteraction(string $dedupeKey, ?int $ticketId, string $userId, string $actionId, string $now): bool
    {
        try {
            $this->pdo->prepare(
                "INSERT INTO support_slack_interactions (dedupe_key, ticket_id, slack_user_id, action_id, outcome, created_at)
                 VALUES (?, ?, ?, ?, 'processing', ?)",
            )->execute([$dedupeKey, $ticketId, $userId, $actionId, $now]);
        } catch (\PDOException $exception) {
            if (Database::isDuplicateKey($exception)) {
                return false;
            }

            throw $exception;
        }

        return true;
    }

    public function setInteractionOutcome(string $dedupeKey, string $outcome): void
    {
        $this->pdo->prepare('UPDATE support_slack_interactions SET outcome = ? WHERE dedupe_key = ?')
            ->execute([mb_substr($outcome, 0, 32), $dedupeKey]);
    }

    /** resolved_at is set when entering RESOLVED, kept while RESOLVED and cleared on reopen. */
    public function applyStaffChange(int $id, TicketStatus $status, ?string $assignee, string $now): void
    {
        $this->pdo->prepare(
            "UPDATE support_tickets
             SET resolved_at = CASE WHEN ? = 'RESOLVED' THEN COALESCE(resolved_at, ?) ELSE NULL END,
                 status = ?, assignee_slack_user_id = ?, version = version + 1, updated_at = ?,
                 sync_state = 'PENDING', sync_attempts = 0, sync_next_attempt_at = ?, sync_last_error = NULL
             WHERE id = ?",
        )->execute([$status->value, $now, $status->value, $assignee, $now, $now, $id]);
    }

    public function purgeInteractionsBefore(string $cutoff): int
    {
        $statement = $this->pdo->prepare('DELETE FROM support_slack_interactions WHERE created_at < ? LIMIT 5000');
        $statement->execute([$cutoff]);

        return $statement->rowCount();
    }

    // ------------------------------------------------------------------ status

    /** @return array{delivery: array<string, int>, sync: array<string, int>, status: array<string, int>} */
    public function counts(): array
    {
        $result = ['delivery' => [], 'sync' => [], 'status' => []];

        foreach (['delivery' => 'delivery_state', 'sync' => 'sync_state', 'status' => 'status'] as $name => $column) {
            foreach ($this->pdo->query("SELECT {$column} AS k, COUNT(*) AS n FROM support_tickets GROUP BY {$column}")->fetchAll() as $row) {
                $result[$name][(string) $row['k']] = (int) $row['n'];
            }
        }

        return $result;
    }

    /** @return list<array<string, mixed>> tickets with delivery or sync problems (no customer content) */
    public function problems(int $limit = 20): array
    {
        return $this->pdo->query(
            "SELECT reference, delivery_state, delivery_attempts, delivery_last_error, delivery_next_attempt_at,
                    sync_state, sync_attempts, sync_last_error
             FROM support_tickets
             WHERE delivery_state <> 'DELIVERED' OR sync_state <> 'IDLE'
             ORDER BY id DESC
             LIMIT " . max(1, $limit),
        )->fetchAll();
    }
}
