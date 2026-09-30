<?php

declare(strict_types=1);

namespace BeryCode\Support\Tests\Integration;

use BeryCode\Support\Slack\TransportResult;
use BeryCode\Support\Tests\Support\DbTestCase;
use BeryCode\Support\Tests\Support\TestDatabase;

final class SlackActionsTest extends DbTestCase
{
    private function lastUpdateBlocks(): string
    {
        $updates = $this->slack->calls('chat.update');
        $this->assertTrue($updates !== [], 'chat.update was called');

        return (string) json_encode(end($updates)['payload']['blocks'], JSON_UNESCAPED_UNICODE);
    }

    private function ephemeralTexts(): array
    {
        return array_map(static fn (array $request) => $request['payload']['text'], $this->slack->responseUrlCalls());
    }

    public function testAssignStartResolveReopenPersistAndUpdateTheOriginalMessage(): void
    {
        $ticket = $this->createDeliveredTicket();
        $id = (int) $ticket['id'];

        $response = $this->clickButton('support_assign_me', $ticket);
        $this->assertSame(200, $response->status);
        $row = $this->ticket($id);
        $this->assertSame(TestDatabase::STAFF, $row['assignee_slack_user_id']);
        $this->assertSame(2, (int) $row['version']);
        $this->assertSame('IDLE', $row['sync_state']);
        $this->assertSame(2, (int) $row['slack_synced_version']);

        $update = $this->slack->calls('chat.update')[0]['payload'];
        $this->assertSame($ticket['slack_message_channel_id'], $update['channel']);
        $this->assertSame($ticket['slack_message_ts'], $update['ts']);
        $this->assertStringContainsString('<@' . TestDatabase::STAFF . '>', $this->lastUpdateBlocks());

        $this->clickButton('support_start', $ticket, ['user' => ['id' => TestDatabase::OTHER_STAFF]]);
        $row = $this->ticket($id);
        $this->assertSame('IN_PROGRESS', $row['status']);
        $this->assertSame(TestDatabase::STAFF, $row['assignee_slack_user_id'], 'start keeps the existing assignee');
        $this->assertStringContainsString('In progress', $this->lastUpdateBlocks());
        $this->assertStringNotContainsString('support_start', $this->lastUpdateBlocks());

        $this->clock->advance(60);
        $this->clickButton('support_resolve', $ticket);
        $row = $this->ticket($id);
        $this->assertSame('RESOLVED', $row['status']);
        $this->assertNotNull($row['resolved_at']);
        $resolvedAt = $row['resolved_at'];
        $this->assertStringContainsString('support_reopen', $this->lastUpdateBlocks());
        $this->assertStringNotContainsString('support_resolve', $this->lastUpdateBlocks());

        $this->clock->advance(60);
        $this->clickButton('support_assign_me', $ticket, ['user' => ['id' => TestDatabase::OTHER_STAFF]]);
        $this->assertSame($resolvedAt, $this->ticket($id)['resolved_at'], 'reassigning a resolved ticket keeps resolved_at');

        $this->clickButton('support_reopen', $ticket);
        $row = $this->ticket($id);
        $this->assertSame('NEW', $row['status']);
        $this->assertNull($row['resolved_at']);
        $this->assertSame(6, (int) $row['version']);

        $history = array_map(
            static fn (array $event) => [$event['action'], $event['actor_id'], $event['from_status'], $event['to_status'], $event['to_assignee']],
            array_values(array_filter($this->events($id), static fn ($event) => in_array($event['action'], ['ASSIGNED', 'STATUS_CHANGED'], true))),
        );
        $this->assertSame([
            ['ASSIGNED', TestDatabase::STAFF, null, null, TestDatabase::STAFF],
            ['STATUS_CHANGED', TestDatabase::OTHER_STAFF, 'NEW', 'IN_PROGRESS', null],
            ['STATUS_CHANGED', TestDatabase::STAFF, 'IN_PROGRESS', 'RESOLVED', null],
            ['ASSIGNED', TestDatabase::OTHER_STAFF, null, null, TestDatabase::OTHER_STAFF],
            ['STATUS_CHANGED', TestDatabase::STAFF, 'RESOLVED', 'NEW', null],
        ], $history);
        $this->assertSame([], $this->ephemeralTexts(), 'successful actions need no ephemeral reply');
    }

    public function testStartOnUnassignedTicketAssignsTheActor(): void
    {
        $ticket = $this->createDeliveredTicket();
        $this->clickButton('support_start', $ticket, ['user' => ['id' => TestDatabase::OTHER_STAFF]]);

        $row = $this->ticket((int) $ticket['id']);
        $this->assertSame('IN_PROGRESS', $row['status']);
        $this->assertSame(TestDatabase::OTHER_STAFF, $row['assignee_slack_user_id']);
        $this->assertCount(1, $this->events((int) $ticket['id'], 'ASSIGNED'));
    }

    public function testReplayedPayloadIsAppliedOnce(): void
    {
        $ticket = $this->createDeliveredTicket();
        $request = $this->slackRequest($this->actionPayload('support_assign_me', $ticket));

        $first = $this->app->slackActionsEndpoint()->handle($request);
        $second = $this->app->slackActionsEndpoint()->handle($request);

        $this->assertSame(200, $first->status);
        $this->assertSame(200, $second->status);
        $this->assertCount(0, $second->deferred, 'replay does no further work');
        $this->assertSame(2, (int) $this->ticket((int) $ticket['id'])['version']);
        $this->assertCount(1, $this->events((int) $ticket['id'], 'ASSIGNED'));
    }

    public function testRepeatedClicksAreIdempotentAndInvalidTransitionsRejected(): void
    {
        $ticket = $this->createDeliveredTicket();

        $this->clickButton('support_start', $ticket);
        $this->clickButton('support_start', $ticket);
        $this->assertSame(2, (int) $this->ticket((int) $ticket['id'])['version'], 'second click is a no-op');
        $this->assertStringContainsString('already in progress', implode(' ', $this->ephemeralTexts()));

        $this->clickButton('support_resolve', $ticket);
        $this->clickButton('support_start', $ticket);
        $row = $this->ticket((int) $ticket['id']);
        $this->assertSame('RESOLVED', $row['status'], 'cannot start a resolved ticket');
        $this->assertSame(3, (int) $row['version']);
        $this->assertStringContainsString('Reopen it first', implode(' ', $this->ephemeralTexts()));
    }

    public function testUnauthorizedAndUntrustedCallbacksCannotChangeState(): void
    {
        $ticket = $this->createDeliveredTicket();
        $other = $this->createDeliveredTicket(['email' => 'other@example.com']);
        $endpoint = $this->app->slackActionsEndpoint();
        $payload = $this->actionPayload('support_resolve', $ticket);

        // Bad signature, wrong secret, stale timestamp, missing headers.
        $this->assertSame(401, $endpoint->handle($this->slackRequest($payload, null, TestDatabase::SIGNING_SECRET, 'v0=' . str_repeat('0', 64)))->status);
        $this->assertSame(401, $endpoint->handle($this->slackRequest($payload, null, 'not-the-secret'))->status);
        $this->assertSame(401, $endpoint->handle($this->slackRequest($payload, $this->clock->now()->getTimestamp() - 600))->status);
        $tampered = $this->slackRequest($payload);
        $this->assertSame(401, $endpoint->handle(new \BeryCode\Support\Http\HttpRequest('POST', $tampered->headers, str_replace('support_resolve', 'support_start', $tampered->body)))->status);

        // Signed, but not from our workspace/app.
        $this->assertSame(403, $endpoint->handle($this->slackRequest($this->actionPayload('support_resolve', $ticket, ['team' => ['id' => 'T0EVIL0001'], 'user' => ['team_id' => 'T0EVIL0001']])))->status);
        $this->assertSame(403, $endpoint->handle($this->slackRequest($this->actionPayload('support_resolve', $ticket, ['api_app_id' => 'A0OTHER001'])))->status);

        // Signed, right workspace, but the user is not on the staff allowlist.
        $response = $this->clickButton('support_resolve', $ticket, ['user' => ['id' => 'U0RANDOM01']]);
        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('not allowed', implode(' ', $this->ephemeralTexts()));

        // Unknown action, non-numeric value, forged ticket id from another message, wrong channel.
        $this->clickButton('support_delete_everything', $ticket);
        $this->clickButton('support_resolve', $ticket, ['actions' => [['value' => '1 OR 1=1']]]);
        $this->clickButton('support_resolve', $ticket, ['actions' => [['value' => (string) $other['id']]]]);
        $this->clickButton('support_resolve', $ticket, ['container' => ['channel_id' => 'C0ELSEWHERE'], 'channel' => ['id' => 'C0ELSEWHERE']]);
        $this->clickButton('support_resolve', $ticket, ['actions' => [['value' => '999999']]]);

        foreach ([$ticket, $other] as $original) {
            $row = $this->ticket((int) $original['id']);
            $this->assertSame('NEW', $row['status']);
            $this->assertNull($row['assignee_slack_user_id']);
            $this->assertSame(1, (int) $row['version']);
        }

        $this->assertCount(0, $this->slack->calls('chat.update'));
        $this->assertStringContainsString('not the current message', implode(' ', $this->ephemeralTexts()));
    }

    public function testEmptyStaffAllowlistAuthorizesNobody(): void
    {
        $this->app = $this->makeApp(['SLACK_ALLOWED_STAFF_USER_IDS' => '']);
        $ticket = $this->createDeliveredTicket();

        $this->clickButton('support_resolve', $ticket);

        $this->assertSame('NEW', $this->ticket((int) $ticket['id'])['status']);
    }

    public function testMessageUpdateFailureKeepsStatusAndIsRetried(): void
    {
        $ticket = $this->createDeliveredTicket();
        $id = (int) $ticket['id'];
        $this->slack->queue(TransportResult::networkError('network:timeout', true));

        $this->clickButton('support_resolve', $ticket);

        $row = $this->ticket($id);
        $this->assertSame('RESOLVED', $row['status'], 'database stays authoritative');
        $this->assertSame('PENDING', $row['sync_state']);
        $this->assertSame(1, (int) $row['slack_synced_version']);

        $this->clock->advance(61);
        $summary = $this->app->delivery()->runDue(25, microtime(true) + 30);
        $this->assertSame(1, $summary['synced']);
        $row = $this->ticket($id);
        $this->assertSame('IDLE', $row['sync_state']);
        $this->assertSame(2, (int) $row['slack_synced_version']);
        $this->assertStringContainsString('support_reopen', $this->lastUpdateBlocks());
    }

    public function testChangesDuringAFailedSyncAreAllRenderedOnRetry(): void
    {
        $ticket = $this->createDeliveredTicket();
        $this->slack->queue(TransportResult::networkError('network:connect_failed', false), TransportResult::networkError('network:connect_failed', false));

        $this->clickButton('support_assign_me', $ticket);
        $this->clickButton('support_start', $ticket);

        $this->clock->advance(3600);
        $this->app->delivery()->runDue(25, microtime(true) + 30);

        $row = $this->ticket((int) $ticket['id']);
        $this->assertSame(3, (int) $row['slack_synced_version']);
        $this->assertSame('IDLE', $row['sync_state']);
        $blocks = $this->lastUpdateBlocks();
        $this->assertStringContainsString('In progress', $blocks);
        $this->assertStringContainsString('<@' . TestDatabase::STAFF . '>', $blocks);
    }

    public function testPermanentUpdateFailureIsRecordedWithoutLosingStatus(): void
    {
        $ticket = $this->createDeliveredTicket();
        $this->slack->queue(TransportResult::json(['ok' => false, 'error' => 'message_not_found']));

        $this->clickButton('support_resolve', $ticket);

        $row = $this->ticket((int) $ticket['id']);
        $this->assertSame('RESOLVED', $row['status']);
        $this->assertSame('FAILED', $row['sync_state']);
        $this->assertSame('slack:message_not_found', $row['sync_last_error']);
        $this->assertCount(1, $this->events((int) $ticket['id'], 'SLACK_SYNC_FAILED'));
    }

    public function testNonButtonInteractionsAreAcknowledgedAndIgnored(): void
    {
        $response = $this->app->slackActionsEndpoint()->handle($this->slackRequest(['type' => 'shortcut', 'team' => ['id' => TestDatabase::TEAM_ID]]));
        $this->assertSame(200, $response->status);
        $this->assertCount(0, $response->deferred);
    }

    public function testActionsBeforeDeliveryAreRejected(): void
    {
        $response = $this->submit($this->validPayload());
        $ticket = $this->ticket((string) $response->json['reference']);
        $ticket['slack_message_channel_id'] = 'C0ACME0001';
        $ticket['slack_message_ts'] = '1700000000.000001';

        $this->clickButton('support_resolve', $ticket);

        $this->assertSame('NEW', $this->ticket((int) $ticket['id'])['status']);
    }
}
