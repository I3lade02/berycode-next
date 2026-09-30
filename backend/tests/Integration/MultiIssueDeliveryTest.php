<?php

declare(strict_types=1);

namespace BeryCode\Support\Tests\Integration;

use BeryCode\Support\Delivery\DeliveryService;
use BeryCode\Support\Slack\TransportResult;
use BeryCode\Support\Tests\Support\DbTestCase;

final class MultiIssueDeliveryTest extends DbTestCase
{
    /** @return array<string, mixed> ticket row */
    private function multiIssueTicket(int $count = 3): array
    {
        $issues = [];

        for ($i = 1; $i <= $count; $i++) {
            $issues[] = self::issuePayload([
                'subject' => 'Issue number ' . $i,
                'description' => 'Details of issue number ' . $i . ' with enough text to be valid.',
                'requestType' => $i === 2 ? 'change_request' : 'bug',
                'priority' => $i === 3 ? 'high' : 'normal',
            ]);
        }

        $response = $this->submit($this->validPayload(['issues' => $issues]));
        $this->assertSame(201, $response->status);

        return $this->ticket((string) $response->json['reference']);
    }

    /** @return list<array{thread_ts: ?string, text: string}> */
    private function posts(): array
    {
        return array_map(static fn (array $request) => [
            'thread_ts' => $request['payload']['thread_ts'] ?? null,
            'text' => $request['payload']['text'],
        ], $this->slack->calls('chat.postMessage'));
    }

    public function testParentMessageThenOneThreadReplyPerIssueInOrder(): void
    {
        $ticket = $this->multiIssueTicket(3);
        $id = (int) $ticket['id'];

        $this->assertSame(DeliveryService::DELIVERED, $this->app->delivery()->deliver($id));

        $ticket = $this->ticket($id);
        $posts = $this->posts();
        $this->assertCount(4, $posts);
        $this->assertNull($posts[0]['thread_ts'], 'first post is the ticket message');

        foreach ([1, 2, 3] as $i) {
            $this->assertSame($ticket['slack_message_ts'], $posts[$i]['thread_ts'], 'reply ' . $i . ' is in the ticket thread');
            $this->assertStringContainsString('issue ' . $i . '/3: Issue number ' . $i, $posts[$i]['text']);
        }

        $parent = $this->slack->calls('chat.postMessage')[0]['payload'];
        $this->assertSame('C0ACME0001', $parent['channel']);
        $this->assertStringContainsString('(+2 more)', $parent['blocks'][0]['text']['text']);
        $this->assertStringContainsString('support_start', (string) json_encode($parent['blocks']), 'buttons live on the parent');

        foreach ($this->issues($id) as $issue) {
            $this->assertNotNull($issue['slack_reply_ts'], 'reply ts stored for issue ' . $issue['position']);
        }

        $this->assertSame('DELIVERED', $ticket['delivery_state']);
        $this->assertStringContainsString('3 issues in thread', (string) $this->events($id, 'SLACK_DELIVERED')[0]['detail']);
    }

    public function testSingleIssueTicketHasNoThreadReplies(): void
    {
        $response = $this->submit($this->validPayload());
        $this->runDeferred($response);

        $this->assertCount(1, $this->posts());
        $this->assertNull($this->posts()[0]['thread_ts']);
        $this->assertNull($this->issues((int) $this->ticket('BC-000001')['id'])[0]['slack_reply_ts']);
    }

    public function testFailureMidThreadResumesWithoutRepostingAndButtonsKeepWorking(): void
    {
        $ticket = $this->multiIssueTicket(3);
        $id = (int) $ticket['id'];
        $this->slack->queue(
            TransportResult::json(['ok' => true, 'channel' => 'C0ACME0001', 'ts' => '1768471200.000100']),
            TransportResult::json(['ok' => true, 'channel' => 'C0ACME0001', 'ts' => '1768471200.000101']),
            TransportResult::networkError('network:connect_failed', false),
        );

        $this->assertSame(DeliveryService::RETRY_SCHEDULED, $this->app->delivery()->deliver($id));

        $ticket = $this->ticket($id);
        $this->assertSame('PENDING', $ticket['delivery_state']);
        $this->assertSame('1768471200.000100', $ticket['slack_message_ts'], 'parent recorded immediately');
        $issues = $this->issues($id);
        $this->assertSame('1768471200.000101', $issues[0]['slack_reply_ts']);
        $this->assertNull($issues[1]['slack_reply_ts']);

        // Staff can already work with the ticket message.
        $this->clickButton('support_start', $ticket);
        $this->assertSame('IN_PROGRESS', $this->ticket($id)['status']);
        $this->assertCount(1, $this->slack->calls('chat.update'));

        $this->clock->advance(61);
        $summary = $this->app->delivery()->runDue(25, microtime(true) + 30);

        $this->assertSame(1, $summary['delivered']);
        $callsPerMessage = ['parent' => 0, 1 => 0, 2 => 0, 3 => 0];

        foreach ($this->posts() as $post) {
            if ($post['thread_ts'] === null) {
                $callsPerMessage['parent']++;
            } elseif (preg_match('/issue (\d)\/3/', $post['text'], $match)) {
                $callsPerMessage[(int) $match[1]]++;
            }
        }

        $this->assertSame(['parent' => 1, 1 => 1, 2 => 2, 3 => 1], $callsPerMessage, 'only the failed reply is sent again');
        $this->assertSame('DELIVERED', $this->ticket($id)['delivery_state']);
    }

    public function testPermanentReplyFailureKeepsTheTicketUsableAndCanBeRequeued(): void
    {
        $ticket = $this->multiIssueTicket(2);
        $id = (int) $ticket['id'];
        $this->slack->queue(
            TransportResult::json(['ok' => true, 'channel' => 'C0ACME0001', 'ts' => '1768471200.000200']),
            TransportResult::json(['ok' => false, 'error' => 'not_in_channel']),
        );

        $this->assertSame(DeliveryService::FAILED, $this->app->delivery()->deliver($id));
        $this->assertSame('slack:not_in_channel', $this->ticket($id)['delivery_last_error']);

        $this->app->tickets()->requeueFailedDeliveries($id, '2026-01-15 10:00:00.000');
        $this->app->delivery()->runDue(25, microtime(true) + 30);

        $this->assertSame('DELIVERED', $this->ticket($id)['delivery_state']);
        $this->assertCount(4, $this->posts(), 'parent once, reply 1 failed once, then replies 1 and 2');
    }

    public function testTimeBudgetHandsRemainingRepliesToTheNextRun(): void
    {
        $ticket = $this->multiIssueTicket(3);
        $id = (int) $ticket['id'];

        $this->assertSame(DeliveryService::RETRY_SCHEDULED, $this->app->delivery()->deliver($id, microtime(true) - 1));
        $this->assertNotNull($this->ticket($id)['slack_message_ts'], 'parent always goes first');
        $this->assertCount(1, $this->posts());

        $this->app->delivery()->runDue(25, microtime(true) + 30);

        $this->assertSame('DELIVERED', $this->ticket($id)['delivery_state']);
        $this->assertCount(4, $this->posts());
    }
}
