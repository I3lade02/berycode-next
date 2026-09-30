<?php

declare(strict_types=1);

namespace BeryCode\Support\Tests\Integration;

use BeryCode\Support\Slack\TransportResult;
use BeryCode\Support\Tests\Support\DbTestCase;
use BeryCode\Support\Tickets\TicketValidator;

final class TicketIntakeTest extends DbTestCase
{
    public function testValidRequestCreatesExactlyOneDurableTicket(): void
    {
        $response = $this->submit($this->validPayload([
            'project' => '  ACME website ',
            'locale' => 'en',
            'issues' => [self::issuePayload(['priority' => 'high'])],
        ]));

        $this->assertSame(201, $response->status);
        $this->assertSame(['ok' => true, 'reference' => 'BC-000001', 'duplicate' => false], $response->json);
        $this->assertSame(1, $this->ticketCount());
        $this->assertCount(0, $this->slack->requests, 'Slack is contacted only after the response');

        $ticket = $this->ticket('BC-000001');
        $this->assertSame('NEW', $ticket['status']);
        $this->assertSame('HIGH', $ticket['priority']);
        $this->assertSame('en', $ticket['locale']);
        $this->assertSame('PENDING', $ticket['delivery_state']);
        $this->assertSame('C0ACME0001', $ticket['slack_channel_id'], 'destination captured from project config');
        $this->assertNull($ticket['assignee_slack_user_id']);

        $events = $this->events((int) $ticket['id']);
        $this->assertCount(1, $events);
        $this->assertSame('CREATED', $events[0]['action']);
        $this->assertSame('CUSTOMER', $events[0]['actor_type']);

        $issues = $this->issues((int) $ticket['id']);
        $this->assertCount(1, $issues);
        $this->assertSame('Contact form returns an error', $issues[0]['subject']);
        $this->assertSame($issues[0]['subject'], $ticket['subject'], 'ticket title is the first issue');
    }

    public function testMultipleIssuesAreSavedAsOneTicketInOrder(): void
    {
        $response = $this->submit($this->validPayload(['issues' => [
            self::issuePayload(['subject' => 'Login page is slow', 'requestType' => 'bug', 'priority' => 'normal']),
            self::issuePayload(['subject' => 'Update the footer text', 'requestType' => 'change_request', 'priority' => 'high']),
            self::issuePayload(['subject' => 'Question about hosting', 'requestType' => 'other', 'priority' => 'normal']),
        ]]));

        $this->assertSame(201, $response->status);
        $this->assertSame(1, $this->ticketCount(), 'one ticket, not three');

        $ticket = $this->ticket('BC-000001');
        $this->assertSame('Login page is slow', $ticket['subject']);
        $this->assertSame('HIGH', $ticket['priority'], 'any high issue makes the ticket high');

        $issues = $this->issues((int) $ticket['id']);
        $this->assertSame(
            [[1, 'BUG', 'NORMAL', 'Login page is slow'], [2, 'CHANGE_REQUEST', 'HIGH', 'Update the footer text'], [3, 'OTHER', 'NORMAL', 'Question about hosting']],
            array_map(static fn (array $issue) => [(int) $issue['position'], $issue['request_type'], $issue['priority'], $issue['subject']], $issues),
        );
    }

    public function testRejectsTooManyOrInvalidIssuesAndSavesNothing(): void
    {
        $tooMany = $this->submit($this->validPayload(['issues' => array_fill(0, 11, self::issuePayload())]));
        $this->assertSame(422, $tooMany->status);
        $this->assertSame(['issues' => 'too_many'], $tooMany->json['fields']);

        $none = $this->submit($this->validPayload(['issues' => []]));
        $this->assertSame(['issues' => 'required'], $none->json['fields']);

        $oneBad = $this->submit($this->validPayload(['issues' => [
            self::issuePayload(),
            self::issuePayload(['description' => 'short']),
        ]]));
        $this->assertSame(['issues.1.description' => 'too_short'], $oneBad->json['fields'], 'one bad issue rejects the whole ticket');

        $this->assertSame(0, $this->ticketCount());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM support_ticket_issues')->fetchColumn());
    }

    public function testTenFullLengthIssuesFitInOneRequest(): void
    {
        $issues = array_map(
            static fn (int $i) => self::issuePayload(['subject' => 'Issue ' . $i . ' ' . str_repeat('ž', 140), 'description' => str_repeat('ěščřžýáíé ', 500)]),
            range(1, 10),
        );
        $body = (string) json_encode($this->validPayload(['issues' => $issues]), JSON_UNESCAPED_UNICODE);
        $this->assertTrue(strlen($body) > 32 * 1024, 'larger than the old 32 KB limit');

        $response = $this->submit($body);

        $this->assertSame(201, $response->status);
        $this->assertCount(10, $this->issues((int) $this->ticket('BC-000001')['id']));
    }

    public function testClientCannotChooseTheSlackChannel(): void
    {
        $response = $this->submit($this->validPayload([
            'slackChannelId' => 'C0ATTACKER',
            'slack_channel_id' => 'C0ATTACKER',
            'channel' => 'C0ATTACKER',
        ]));
        $this->runDeferred($response);

        $this->assertSame('C0ACME0001', $this->ticket((string) $response->json['reference'])['slack_channel_id']);
        $this->assertSame('C0ACME0001', $this->slack->calls('chat.postMessage')[0]['payload']['channel']);
    }

    public function testRejectsInvalidInputWithFieldCodesAndSavesNothing(): void
    {
        $response = $this->submit($this->validPayload([
            'name' => 'J',
            'email' => 'nope',
            'issues' => [self::issuePayload(['subject' => 'Hi', 'description' => 'too short', 'requestType' => 'feature'])],
        ]));

        $this->assertSame(422, $response->status);
        $this->assertSame('validation_failed', $response->json['error']);
        $this->assertSame([
            'name' => 'too_short',
            'email' => 'invalid',
            'issues.0.subject' => 'too_short',
            'issues.0.description' => 'too_short',
            'issues.0.requestType' => 'invalid',
        ], $response->json['fields']);
        $this->assertSame(0, $this->ticketCount());
    }

    public function testRejectsUnknownInactiveAndPrivateProjectsHelpfully(): void
    {
        $unknown = $this->submit($this->validPayload(['project' => 'acme-wbe']));
        $this->assertSame(422, $unknown->status);
        $this->assertSame(['project' => 'project_not_found'], $unknown->json['fields']);
        $this->assertSame(['Acme Web Studio'], $unknown->json['suggestions']);

        $inactive = $this->submit($this->validPayload(['project' => 'Old Site']));
        $this->assertSame(['project' => 'project_not_found'], $inactive->json['fields']);

        $private = $this->submit($this->validPayload(['project' => 'nordik']));
        $this->assertSame(['project' => 'project_not_found'], $private->json['fields']);
        $this->assertFalse(isset($private->json['suggestions']), 'no directory of private projects');
        $this->assertStringNotContainsString('G0NORD0001', (string) json_encode($private->json));

        $this->assertSame(0, $this->ticketCount());
    }

    public function testProtocolLevelRejections(): void
    {
        $this->assertSame(405, $this->submit($this->validPayload(), [], '203.0.113.10', 'GET')->status);
        $this->assertSame(415, $this->submit($this->validPayload(), ['content-type' => 'text/plain'])->status);
        $this->assertSame(413, $this->submit('', [], '203.0.113.10', 'POST', true)->status);
        $this->assertSame(400, $this->submit('{not json')->status);
        $this->assertSame(400, $this->submit('[1,2,3]')->status);
        $this->assertSame(400, $this->submit('"string"')->status);
        $this->assertSame(0, $this->ticketCount());
    }

    public function testHoneypotRejectsWithoutSaving(): void
    {
        $response = $this->submit($this->validPayload(['website' => 'http://spam.example']));

        $this->assertSame(422, $response->status);
        $this->assertSame(['ok' => false, 'error' => 'rejected'], $response->json);
        $this->assertSame(0, $this->ticketCount());

        $nonString = $this->submit($this->validPayload(['website' => ['x']]));
        $this->assertSame(422, $nonString->status);
    }

    public function testOriginAllowlist(): void
    {
        $this->app = $this->makeApp(['SUPPORT_ALLOWED_ORIGINS' => 'https://berycode.cz, https://www.berycode.cz']);

        $this->assertSame(403, $this->submit($this->validPayload(), ['origin' => 'https://evil.example'])->status);
        $this->assertSame(201, $this->submit($this->validPayload(), ['origin' => 'https://berycode.cz'])->status);
        $this->assertSame(201, $this->submit($this->validPayload())->status, 'non-browser clients without Origin still validated normally');
    }

    public function testDuplicateSubmissionReturnsTheSameTicket(): void
    {
        $payload = $this->validPayload();

        $first = $this->submit($payload);
        $second = $this->submit($payload);
        $third = $this->submit(array_merge($payload, ['email' => strtoupper($payload['email'])]));

        $this->assertSame(201, $first->status);
        $this->assertSame(200, $second->status);
        $this->assertSame(['ok' => true, 'reference' => 'BC-000001', 'duplicate' => true], $second->json);
        $this->assertSame('BC-000001', $third->json['reference']);
        $this->assertSame(1, $this->ticketCount());

        // Retries still trigger delivery if the first attempt never completed.
        $this->runDeferred($second);
        $this->runDeferred($first);
        $this->assertCount(1, $this->slack->calls('chat.postMessage'), 'delivered once');
    }

    public function testReusedKeyWithDifferentContentIsAConflict(): void
    {
        $payload = $this->validPayload();
        $this->submit($payload);

        $conflict = $this->submit(array_merge($payload, [
            'issues' => [$payload['issues'][0], self::issuePayload(['subject' => 'An extra issue added later'])],
        ]));

        $this->assertSame(409, $conflict->status);
        $this->assertSame('idempotency_conflict', $conflict->json['error']);
        $this->assertSame(1, $this->ticketCount());
    }

    public function testConcurrentInsertOfTheSameSubmissionIsResolved(): void
    {
        [$submission] = TicketValidator::validate($this->validPayload());
        $project = $this->app->projectResolver()->resolve('acme-web')->project;
        $service = $this->app->ticketService();

        // Both requests passed the "does this key exist?" check before either inserted.
        $first = $service->create($submission, $project);
        $second = $service->create($submission, $project);

        $this->assertFalse($first->duplicate);
        $this->assertTrue($second->duplicate);
        $this->assertSame($first->reference, $second->reference);
        $this->assertSame(1, $this->ticketCount());
    }

    public function testRateLimitsPerIpEmailAndGlobally(): void
    {
        $this->app = $this->makeApp([
            'SUPPORT_RATE_LIMIT_IP_PER_10_MIN' => '3',
            'SUPPORT_RATE_LIMIT_EMAIL_PER_HOUR' => '1',
            'SUPPORT_RATE_LIMIT_GLOBAL_PER_HOUR' => '4',
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->assertSame(201, $this->submit($this->validPayload(['email' => "user{$i}@example.com"]), [], '198.51.100.1')->status);
        }

        $limited = $this->submit($this->validPayload(), [], '198.51.100.1');
        $this->assertSame(429, $limited->status);
        $this->assertSame('rate_limited', $limited->json['error']);
        $this->assertTrue((int) $limited->headers['Retry-After'] > 0);

        // Per-email limit from different IPs.
        $this->assertSame(201, $this->submit($this->validPayload(['email' => 'same@example.com']), [], '198.51.100.2')->status);
        $this->assertSame(429, $this->submit($this->validPayload(['email' => 'same@example.com']), [], '198.51.100.3')->status);

        // Global cap: 4 tickets per hour.
        $this->assertSame(429, $this->submit($this->validPayload(['email' => 'fresh@example.com']), [], '198.51.100.4')->status);
        $this->assertSame(4, $this->ticketCount());

        // Windows expire.
        $this->clock->advance(3600);
        $this->assertSame(201, $this->submit($this->validPayload(), [], '198.51.100.1')->status);
    }

    public function testClientIpHeaderIsOnlyTrustedWhenConfigured(): void
    {
        $this->app = $this->makeApp(['SUPPORT_RATE_LIMIT_IP_PER_10_MIN' => '1']);
        $this->assertSame(201, $this->submit($this->validPayload(), ['x-forwarded-for' => '192.0.2.1'], '10.0.0.1')->status);
        $this->assertSame(429, $this->submit($this->validPayload(), ['x-forwarded-for' => '192.0.2.2'], '10.0.0.1')->status, 'spoofed header ignored');

        $this->app = $this->makeApp(['SUPPORT_RATE_LIMIT_IP_PER_10_MIN' => '1', 'SUPPORT_CLIENT_IP_HEADER' => 'X-Real-IP']);
        $this->assertSame(201, $this->submit($this->validPayload(), ['x-real-ip' => '192.0.2.10'], '10.0.0.2')->status);
        $this->assertSame(201, $this->submit($this->validPayload(), ['x-real-ip' => '192.0.2.11'], '10.0.0.2')->status);
    }

    public function testSlackOutageNeverLosesTheTicketOrLeaksDetails(): void
    {
        $this->slack->queue(TransportResult::networkError('network:connect_failed', false));
        $response = $this->submit($this->validPayload());
        $this->runDeferred($response);

        $this->assertSame(201, $response->status);
        $this->assertSame(['ok', 'reference', 'duplicate'], array_keys($response->json), 'no delivery state in the customer response');

        $ticket = $this->ticket('BC-000001');
        $this->assertSame('PENDING', $ticket['delivery_state']);
        $this->assertSame(1, (int) $ticket['delivery_attempts']);
    }

    public function testLogsContainIdentifiersButNoCustomerContentOrSecrets(): void
    {
        $payload = $this->validPayload([
            'name' => 'Zdeňka Tajná',
            'email' => 'very.private@example.org',
            'issues' => [
                self::issuePayload(['description' => 'Private details: invoice 4455-SECRET for customer Tajná, please do not log.']),
                self::issuePayload(['subject' => 'Confidential subject 7788', 'description' => 'Second private description, also not for the logs.']),
            ],
        ]);
        $this->slack->queue(TransportResult::json(['ok' => false, 'error' => 'not_in_channel']));
        $response = $this->submit($payload);
        $this->runDeferred($response);
        $this->submit($this->validPayload(['email' => 'invalid-address-in-log@']));

        $logs = $this->allLogs();
        $this->assertStringContainsString('BC-000001', $logs);
        $this->assertStringContainsString('not_in_channel', $logs);

        foreach (['Zdeňka', 'very.private@example.org', '4455-SECRET', 'Confidential subject 7788', 'invalid-address-in-log', 'xoxb-test-token-not-real', 'test-signing-secret'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $logs, 'log leaked ' . $sensitive);
        }
    }
}
