<?php

declare(strict_types=1);

namespace BeryCode\Support\Tests\Integration;

use BeryCode\Support\Delivery\DeliveryService;
use BeryCode\Support\Slack\TransportResult;
use BeryCode\Support\Tests\Support\DbTestCase;
use BeryCode\Support\Time;

final class DeliveryTest extends DbTestCase
{
    private function pendingTicket(array $overrides = []): array
    {
        $response = $this->submit($this->validPayload($overrides));
        $this->assertSame(201, $response->status);

        return $this->ticket((string) $response->json['reference']);
    }

    private function runDue(): array
    {
        return $this->app->delivery()->runDue(25, microtime(true) + 30);
    }

    public function testSuccessfulDeliveryStoresChannelAndTimestamp(): void
    {
        $ticket = $this->pendingTicket();
        $this->slack->queue(TransportResult::json(['ok' => true, 'channel' => 'C0ACME0001', 'ts' => '1768471200.123456']));

        $this->assertSame(DeliveryService::DELIVERED, $this->app->delivery()->deliver((int) $ticket['id']));

        $ticket = $this->ticket((int) $ticket['id']);
        $this->assertSame('DELIVERED', $ticket['delivery_state']);
        $this->assertSame('C0ACME0001', $ticket['slack_message_channel_id']);
        $this->assertSame('1768471200.123456', $ticket['slack_message_ts']);
        $this->assertSame('IDLE', $ticket['sync_state']);
        $this->assertSame(1, (int) $ticket['slack_synced_version']);
        $this->assertCount(1, $this->events((int) $ticket['id'], 'SLACK_DELIVERED'));

        $request = $this->slack->calls('chat.postMessage')[0];
        $this->assertSame('https://slack.com/api/chat.postMessage', $request['url']);
        $this->assertSame('Bearer xoxb-test-token-not-real', $request['headers']['Authorization']);
        $this->assertSame('C0ACME0001', $request['payload']['channel']);
        $this->assertStringContainsString('BC-000001', $request['payload']['text']);
        $this->assertTrue(count($request['payload']['blocks']) > 3);

        // Confirmed deliveries are never posted again.
        $this->runDue();
        $this->assertSame(DeliveryService::NOT_CLAIMED, $this->app->delivery()->deliver((int) $ticket['id']));
        $this->assertCount(1, $this->slack->calls('chat.postMessage'));
    }

    public function testTransientFailureIsRetriedWithBackoff(): void
    {
        $ticket = $this->pendingTicket();
        $id = (int) $ticket['id'];
        $this->slack->queue(TransportResult::networkError('network:connect_failed', false));

        $this->assertSame(DeliveryService::RETRY_SCHEDULED, $this->app->delivery()->deliver($id));
        $ticket = $this->ticket($id);
        $this->assertSame('PENDING', $ticket['delivery_state']);
        $this->assertSame(1, (int) $ticket['delivery_attempts']);
        $this->assertSame('slack:network:connect_failed', $ticket['delivery_last_error']);
        $this->assertSame(Time::db(Time::plusSeconds($this->clock->now(), 60)), $ticket['delivery_next_attempt_at']);

        // Not due yet.
        $this->assertSame(0, $this->runDue()['delivered']);
        $this->assertCount(1, $this->slack->calls('chat.postMessage'));

        $this->clock->advance(61);
        $summary = $this->runDue();
        $this->assertSame(1, $summary['delivered']);
        $ticket = $this->ticket($id);
        $this->assertSame('DELIVERED', $ticket['delivery_state']);
        $this->assertSame(2, (int) $ticket['delivery_attempts']);
        $this->assertNull($ticket['delivery_last_error']);
        $this->assertCount(1, $this->events($id, 'SLACK_DELIVERY_RETRY_SCHEDULED'));
    }

    public function testRateLimitRespectsRetryAfterAndStopsTheBatch(): void
    {
        $first = $this->pendingTicket(['email' => 'a@example.com']);
        $second = $this->pendingTicket(['email' => 'b@example.com']);
        $this->slack->queue(new TransportResult(429, '', ['retry-after' => '300']));

        $summary = $this->runDue();

        $this->assertSame(1, $summary['retry_scheduled']);
        $this->assertCount(1, $this->slack->calls('chat.postMessage'), 'second ticket not attempted while rate limited');
        $this->assertSame(Time::db(Time::plusSeconds($this->clock->now(), 300)), $this->ticket((int) $first['id'])['delivery_next_attempt_at']);
        $this->assertSame('PENDING', $this->ticket((int) $second['id'])['delivery_state']);
        $this->assertSame(0, (int) $this->ticket((int) $second['id'])['delivery_attempts']);
    }

    public function testConfigurationAndPermissionErrorsAreNotRetried(): void
    {
        $ticket = $this->pendingTicket();
        $id = (int) $ticket['id'];
        $this->slack->queue(TransportResult::json(['ok' => false, 'error' => 'not_in_channel']));

        $this->assertSame(DeliveryService::FAILED, $this->app->delivery()->deliver($id));
        $ticket = $this->ticket($id);
        $this->assertSame('FAILED', $ticket['delivery_state']);
        $this->assertSame('slack:not_in_channel', $ticket['delivery_last_error']);
        $this->assertSame('NEW', $ticket['status'], 'ticket itself is untouched');

        $this->clock->advance(86400);
        $this->runDue();
        $this->assertCount(1, $this->slack->calls('chat.postMessage'), 'permanent failures wait for a human');

        // After inviting the bot, the operator requeues.
        $this->assertSame(1, $this->app->tickets()->requeueFailedDeliveries($id, Time::db($this->clock->now())));
        $this->assertSame(1, $this->runDue()['delivered']);
        $this->assertSame('DELIVERED', $this->ticket($id)['delivery_state']);
    }

    public function testMissingBotTokenFailsClearlyWithoutFakingSuccess(): void
    {
        $this->app = $this->makeApp(['SLACK_BOT_TOKEN' => '']);
        $ticket = $this->pendingTicket();

        $this->assertSame(DeliveryService::FAILED, $this->app->delivery()->deliver((int) $ticket['id']));
        $ticket = $this->ticket((int) $ticket['id']);
        $this->assertSame('FAILED', $ticket['delivery_state']);
        $this->assertSame('slack:config_missing_bot_token', $ticket['delivery_last_error']);
        $this->assertNull($ticket['slack_message_ts']);
        $this->assertCount(0, $this->slack->requests);
    }

    public function testOkWithoutTimestampIsNotTreatedAsDeliveredOrRetried(): void
    {
        $ticket = $this->pendingTicket();
        $this->slack->queue(TransportResult::json(['ok' => true, 'channel' => 'C0ACME0001']));

        $this->assertSame(DeliveryService::FAILED, $this->app->delivery()->deliver((int) $ticket['id']));
        $this->assertSame('slack:missing_ts_in_response', $this->ticket((int) $ticket['id'])['delivery_last_error']);
    }

    public function testAmbiguousTimeoutIsRetriedAndRecordedAsAmbiguous(): void
    {
        $ticket = $this->pendingTicket();
        $this->slack->queue(TransportResult::networkError('network:timeout', true), new TransportResult(502, 'bad gateway'));

        $this->app->delivery()->deliver((int) $ticket['id']);
        $this->assertStringContainsString('ambiguous', (string) $this->ticket((int) $ticket['id'])['delivery_last_error']);

        $this->clock->advance(61);
        $this->runDue();
        $ticket = $this->ticket((int) $ticket['id']);
        $this->assertSame('slack:http_502 (ambiguous: request may have reached Slack)', $ticket['delivery_last_error']);
        $this->assertSame(2, (int) $ticket['delivery_attempts']);
    }

    public function testRetriesAreBoundedThenMarkedFailed(): void
    {
        $ticket = $this->pendingTicket();
        $id = (int) $ticket['id'];

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->slack->queue(TransportResult::json(['ok' => false, 'error' => 'internal_error']));
            $this->clock->advance(8 * 3600);
            $this->runDue();
        }

        $ticket = $this->ticket($id);
        $this->assertSame('FAILED', $ticket['delivery_state']);
        $this->assertSame(10, (int) $ticket['delivery_attempts']);
        $this->assertStringContainsString('retries exhausted', (string) $ticket['delivery_last_error']);

        $this->clock->advance(86400);
        $this->runDue();
        $this->assertCount(10, $this->slack->calls('chat.postMessage'));
    }

    public function testExpiredLeaseOfCrashedWorkerIsTakenOverButLiveLeaseIsRespected(): void
    {
        $ticket = $this->pendingTicket();
        $id = (int) $ticket['id'];
        $now = $this->clock->now();

        // Simulate a worker that claimed the ticket and died before recording the result.
        $this->assertSame('PENDING', $this->app->tickets()->claimDelivery($id, Time::db($now), Time::db(Time::plusSeconds($now, 120))));

        $this->assertSame(DeliveryService::NOT_CLAIMED, $this->app->delivery()->deliver($id), 'live lease blocks a second worker');
        $this->assertCount(0, $this->slack->requests);

        $this->clock->advance(121);
        $this->assertSame(1, $this->runDue()['delivered']);
        $this->assertCount(1, $this->events($id, 'SLACK_DELIVERY_LEASE_EXPIRED'));
        $this->assertSame(2, (int) $this->ticket($id)['delivery_attempts']);
    }
}
