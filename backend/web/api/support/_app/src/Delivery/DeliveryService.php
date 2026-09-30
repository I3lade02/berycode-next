<?php

declare(strict_types=1);

namespace BeryCode\Support\Delivery;

use BeryCode\Support\Clock;
use BeryCode\Support\Logger;
use BeryCode\Support\Slack\SlackClient;
use BeryCode\Support\Slack\SlackMessageBuilder;
use BeryCode\Support\Slack\SlackResponse;
use BeryCode\Support\Tickets\TicketRepository;
use BeryCode\Support\Time;

/**
 * Durable Slack delivery (chat.postMessage) and synchronisation
 * (chat.update). The database row is the queue: work is claimed with a lease so
 * concurrent workers (the request that created the ticket, overlapping cron
 * runs) never process the same ticket at once, and a crashed worker's lease
 * simply expires.
 */
final class DeliveryService
{
    public const DELIVERED = 'delivered';
    public const RETRY_SCHEDULED = 'retry_scheduled';
    public const FAILED = 'failed';
    public const NOT_CLAIMED = 'not_claimed';
    public const SYNCED = 'synced';

    private const DELIVERY_LEASE_SECONDS = 120;
    /** Soft limit for one delivery run (a parent message plus up to 10 thread replies). */
    private const DELIVERY_TIME_BUDGET_SECONDS = 20;
    private const SYNC_LEASE_SECONDS = 60;
    /** Each extra pass means staff changed the ticket during the previous update. */
    private const MAX_SYNC_PASSES = 10;

    /** Set when Slack rate-limits us, so a batch run can stop early. */
    private bool $rateLimited = false;

    public function __construct(
        private TicketRepository $tickets,
        private SlackClient $slack,
        private SlackMessageBuilder $builder,
        private Clock $clock,
        private Logger $logger,
        private RetryPolicy $deliveryPolicy = new RetryPolicy(),
        private RetryPolicy $syncPolicy = new RetryPolicy(8),
    ) {
    }

    /**
     * Posts the ticket message, then (multi-issue tickets) one thread reply per
     * issue. Every post is recorded as soon as Slack confirms it, so a retry
     * resumes where the previous attempt stopped instead of starting over.
     */
    public function deliver(int $ticketId, ?float $deadline = null): string
    {
        $deadline ??= microtime(true) + self::DELIVERY_TIME_BUDGET_SECONDS;
        $now = $this->clock->now();
        $previousState = $this->tickets->claimDelivery(
            $ticketId,
            Time::db($now),
            Time::db(Time::plusSeconds($now, self::DELIVERY_LEASE_SECONDS)),
        );

        if ($previousState === null) {
            return self::NOT_CLAIMED;
        }

        $ticket = $this->tickets->view($ticketId);

        if ($ticket === null) {
            return self::NOT_CLAIMED;
        }

        if ($previousState === 'SENDING') {
            // A previous worker died mid-delivery; its message may or may not exist.
            $this->tickets->addEvent($ticketId, 'SLACK_DELIVERY_LEASE_EXPIRED', TicketRepository::ACTOR_SYSTEM, null, Time::db($now), [], 'previous attempt outcome unknown; retrying');
            $this->logger->warning('delivery_lease_expired', ['ticket' => $ticket->reference]);
        }

        // 1. The ticket message (thread parent), unless an earlier attempt posted it.
        if ($ticket->slackMessageTs === null) {
            $message = $this->builder->build($ticket);
            $response = $this->slack->call('chat.postMessage', [
                'channel' => $ticket->slackChannelId,
                'text' => $message['text'],
                'blocks' => $message['blocks'],
                'metadata' => $message['metadata'],
                'unfurl_links' => false,
                'unfurl_media' => false,
            ]);

            $channel = $response->data['channel'] ?? null;
            $ts = $response->data['ts'] ?? null;

            if (!$response->ok || !is_string($channel) || $channel === '' || !is_string($ts) || $ts === '') {
                return $this->handleDeliveryFailure($ticketId, $ticket->reference, $ticket->deliveryAttempts, self::withoutTs($response));
            }

            $this->tickets->markParentPosted($ticketId, $channel, $ts, $ticket->version, Time::db($this->clock->now()));
            $ticket = $this->tickets->view($ticketId) ?? $ticket;
        }

        // 2. Multi-issue tickets: each issue's details as a reply in the thread.
        $replies = 0;

        if ($ticket->usesThread() && $ticket->slackMessageChannelId !== null && $ticket->slackMessageTs !== null) {
            foreach ($ticket->issues as $issue) {
                if ($issue->slackReplyTs !== null) {
                    continue;
                }

                if (microtime(true) >= $deadline) {
                    // Out of time for this run: the next run continues with the remaining replies.
                    $this->tickets->scheduleDeliveryRetry($ticketId, Time::db($this->clock->now()), 'thread replies pending');

                    return self::RETRY_SCHEDULED;
                }

                $reply = $this->builder->buildIssueReply($ticket, $issue);
                $response = $this->slack->call('chat.postMessage', [
                    'channel' => $ticket->slackMessageChannelId,
                    'thread_ts' => $ticket->slackMessageTs,
                    'text' => $reply['text'],
                    'blocks' => $reply['blocks'],
                    'metadata' => $reply['metadata'],
                    'unfurl_links' => false,
                    'unfurl_media' => false,
                ]);
                $ts = $response->data['ts'] ?? null;

                if (!$response->ok || !is_string($ts) || $ts === '') {
                    return $this->handleDeliveryFailure($ticketId, $ticket->reference, $ticket->deliveryAttempts, self::withoutTs($response));
                }

                $this->tickets->markIssueReplyPosted($issue->id, $ts);
                $replies++;
            }
        }

        $now = Time::db($this->clock->now());

        if ($this->tickets->markDelivered($ticketId, $now)) {
            $detail = 'channel ' . $ticket->slackMessageChannelId . ($ticket->usesThread() ? '; ' . count($ticket->issues) . ' issues in thread' : '');
            $this->tickets->addEvent($ticketId, 'SLACK_DELIVERED', TicketRepository::ACTOR_SYSTEM, null, $now, [], $detail);
            $this->logger->info('delivery_succeeded', ['ticket' => $ticket->reference, 'attempt' => $ticket->deliveryAttempts, 'thread_replies' => $replies]);
        }

        return self::DELIVERED;
    }

    /** Slack accepted a post but returned no ts: retrying could duplicate it, so it is a permanent failure. */
    private static function withoutTs(SlackResponse $response): SlackResponse
    {
        return $response->ok ? SlackResponse::localFailure('missing_ts_in_response') : $response;
    }

    /**
     * Re-renders the ticket from the database and updates the original message.
     * Loops while staff changed the ticket during the update, so Slack converges
     * on the latest state.
     */
    public function sync(int $ticketId): string
    {
        $outcome = self::NOT_CLAIMED;

        for ($pass = 0; $pass < self::MAX_SYNC_PASSES; $pass++) {
            $now = $this->clock->now();

            if (!$this->tickets->claimSync($ticketId, Time::db($now), Time::db(Time::plusSeconds($now, self::SYNC_LEASE_SECONDS)))) {
                return $outcome;
            }

            $ticket = $this->tickets->view($ticketId);

            if ($ticket === null || $ticket->slackMessageChannelId === null || $ticket->slackMessageTs === null) {
                return $outcome;
            }

            $message = $this->builder->build($ticket);
            $response = $this->slack->call('chat.update', [
                'channel' => $ticket->slackMessageChannelId,
                'ts' => $ticket->slackMessageTs,
                'text' => $message['text'],
                'blocks' => $message['blocks'],
                'metadata' => $message['metadata'],
            ]);

            if (!$response->ok) {
                return $this->handleSyncFailure($ticketId, $ticket->reference, $ticket->syncAttempts, $response);
            }

            $outcome = self::SYNCED;

            if (!$this->tickets->markSynced($ticketId, $ticket->version, Time::db($this->clock->now()))) {
                return $outcome;
            }
        }

        return $outcome;
    }

    /**
     * Processes due deliveries and syncs within a time budget. This is what the
     * cron endpoint and `support.php deliveries:run` execute.
     *
     * @return array<string, int>
     */
    public function runDue(int $limit, float $deadline): array
    {
        $summary = ['delivered' => 0, 'retry_scheduled' => 0, 'failed' => 0, 'synced' => 0, 'sync_retry_scheduled' => 0, 'sync_failed' => 0, 'skipped' => 0];
        $this->rateLimited = false;

        foreach ($this->tickets->dueDeliveryIds(Time::db($this->clock->now()), $limit) as $id) {
            if ($this->rateLimited || microtime(true) >= $deadline) {
                break;
            }

            $outcome = $this->deliver($id, $deadline);
            $summary[$outcome === self::NOT_CLAIMED ? 'skipped' : $outcome]++;
        }

        foreach ($this->tickets->dueSyncIds(Time::db($this->clock->now()), $limit) as $id) {
            if ($this->rateLimited || microtime(true) >= $deadline) {
                break;
            }

            $outcome = $this->sync($id);
            $key = match ($outcome) {
                self::SYNCED => 'synced',
                self::RETRY_SCHEDULED => 'sync_retry_scheduled',
                self::FAILED => 'sync_failed',
                default => 'skipped',
            };
            $summary[$key]++;
        }

        return $summary;
    }

    private function handleDeliveryFailure(int $ticketId, string $reference, int $attempts, SlackResponse $response): string
    {
        $now = $this->clock->now();
        $error = $response->errorSummary();
        $this->rateLimited = $this->rateLimited || $response->error === 'ratelimited';

        if (!$response->retryable || $this->deliveryPolicy->exhausted($attempts)) {
            $final = $response->retryable ? 'retries exhausted; last error ' . $error : $error;
            $this->tickets->markDeliveryFailed($ticketId, $final);
            $this->tickets->addEvent($ticketId, 'SLACK_DELIVERY_FAILED', TicketRepository::ACTOR_SYSTEM, null, Time::db($now), [], $final);
            $this->logger->error('delivery_failed', ['ticket' => $reference, 'attempt' => $attempts, 'error' => $final]);

            return self::FAILED;
        }

        $delay = $this->deliveryPolicy->delayAfter($attempts, $response->retryAfterSeconds);
        $next = Time::db(Time::plusSeconds($now, $delay));
        $this->tickets->scheduleDeliveryRetry($ticketId, $next, $error);
        $this->tickets->addEvent($ticketId, 'SLACK_DELIVERY_RETRY_SCHEDULED', TicketRepository::ACTOR_SYSTEM, null, Time::db($now), [], $error . '; next attempt in ' . $delay . 's');
        $this->logger->warning('delivery_retry_scheduled', ['ticket' => $reference, 'attempt' => $attempts, 'error' => $error, 'delay_s' => $delay]);

        return self::RETRY_SCHEDULED;
    }

    private function handleSyncFailure(int $ticketId, string $reference, int $attempts, SlackResponse $response): string
    {
        $now = $this->clock->now();
        $error = $response->errorSummary();
        $this->rateLimited = $this->rateLimited || $response->error === 'ratelimited';

        if (!$response->retryable || $this->syncPolicy->exhausted($attempts)) {
            $final = $response->retryable ? 'retries exhausted; last error ' . $error : $error;
            $this->tickets->markSyncFailed($ticketId, $final);
            $this->tickets->addEvent($ticketId, 'SLACK_SYNC_FAILED', TicketRepository::ACTOR_SYSTEM, null, Time::db($now), [], $final);
            $this->logger->error('sync_failed', ['ticket' => $reference, 'attempt' => $attempts, 'error' => $final]);

            return self::FAILED;
        }

        $delay = $this->syncPolicy->delayAfter($attempts, $response->retryAfterSeconds);
        $this->tickets->scheduleSyncRetry($ticketId, Time::db(Time::plusSeconds($now, $delay)), $error);
        $this->logger->warning('sync_retry_scheduled', ['ticket' => $reference, 'attempt' => $attempts, 'error' => $error, 'delay_s' => $delay]);

        return self::RETRY_SCHEDULED;
    }
}
