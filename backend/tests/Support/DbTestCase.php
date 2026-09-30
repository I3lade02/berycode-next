<?php

declare(strict_types=1);

namespace BeryCode\Support\Tests\Support;

use BeryCode\Support\App;
use BeryCode\Support\Config;
use BeryCode\Support\Http\HttpRequest;
use BeryCode\Support\Http\HttpResponse;
use BeryCode\Support\Logger;
use BeryCode\Support\Projects\ProjectConfigSync;
use BeryCode\Support\Slack\SlackSignatureVerifier;
use PDO;

/**
 * Base for tests that use the real test database, a fixed clock, a scripted
 * Slack transport and a captured log.
 */
abstract class DbTestCase extends TestCase
{
    protected PDO $pdo;
    protected FixedClock $clock;
    protected FakeTransport $slack;
    protected App $app;

    /** @var list<string> */
    protected array $logs = [];

    private static int $actionCounter = 0;

    public function setUp(): void
    {
        $this->pdo = TestDatabase::pdo();
        TestDatabase::truncate($this->pdo);
        $this->clock = new FixedClock();
        $this->slack = new FakeTransport();
        $this->logs = [];
        $this->app = $this->makeApp();
        $this->seedProjects();
    }

    /** @param array<string, string> $overrides */
    protected function makeApp(array $overrides = []): App
    {
        $logs = &$this->logs;

        return new App(
            Config::fromArray(array_merge(TestDatabase::baseConfig(), $overrides)),
            $this->clock,
            new Logger(static function (string $line) use (&$logs): void {
                $logs[] = $line;
            }),
            $this->pdo,
            $this->slack,
        );
    }

    protected function seedProjects(): void
    {
        $json = json_decode((string) file_get_contents(dirname(__DIR__) . '/fixtures/projects.json'), true);
        $this->app->projectConfigSync()->apply(ProjectConfigSync::parse($json));
    }

    /** @return array<string, mixed> */
    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jana Nováková',
            'email' => 'jana.novakova@example.com',
            'project' => 'acme-web',
            'issues' => [self::issuePayload()],
            'locale' => 'cs',
            'idempotencyKey' => self::uuid(),
            'website' => '',
        ], $overrides);
    }

    /** @return array<string, mixed> */
    protected static function issuePayload(array $overrides = []): array
    {
        return array_merge([
            'requestType' => 'bug',
            'priority' => 'normal',
            'subject' => 'Contact form returns an error',
            'description' => 'After submitting the contact form the page shows error 500 and nothing is saved.',
        ], $overrides);
    }

    /** @return list<array<string, mixed>> */
    protected function issues(int $ticketId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM support_ticket_issues WHERE ticket_id = ? ORDER BY position');
        $statement->execute([$ticketId]);

        return $statement->fetchAll();
    }

    /**
     * @param array<string, mixed>|string $payload array (JSON-encoded) or raw body
     * @param array<string, string> $headers
     */
    protected function submit(array|string $payload, array $headers = [], string $ip = '203.0.113.10', string $method = 'POST', bool $tooLarge = false): HttpResponse
    {
        $body = is_string($payload) ? $payload : (string) json_encode($payload);

        return $this->app->ticketEndpoint()->handle(new HttpRequest(
            $method,
            array_merge(['content-type' => 'application/json'], $headers),
            $tooLarge ? '' : $body,
            [],
            ['REMOTE_ADDR' => $ip],
            $tooLarge,
        ));
    }

    protected function runDeferred(HttpResponse $response): void
    {
        foreach ($response->deferred as $task) {
            $task();
        }
    }

    /** Submits a valid request and runs its deferred first delivery. */
    protected function createDeliveredTicket(array $overrides = []): array
    {
        $response = $this->submit($this->validPayload($overrides));
        $this->assertSame(201, $response->status, 'ticket created');
        $this->runDeferred($response);
        $ticket = $this->ticket((string) $response->json['reference']);
        $this->assertSame('DELIVERED', $ticket['delivery_state'], 'delivered');

        return $ticket;
    }

    /** @return array<string, mixed> */
    protected function ticket(int|string $idOrReference): array
    {
        $column = is_int($idOrReference) ? 'id' : 'reference';
        $statement = $this->pdo->prepare("SELECT * FROM support_tickets WHERE {$column} = ?");
        $statement->execute([$idOrReference]);
        $row = $statement->fetch();
        $this->assertTrue($row !== false, 'ticket ' . $idOrReference . ' exists');

        return $row;
    }

    protected function ticketCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM support_tickets')->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    protected function events(int $ticketId, ?string $action = null): array
    {
        $sql = 'SELECT * FROM support_ticket_events WHERE ticket_id = ?';
        $params = [$ticketId];

        if ($action !== null) {
            $sql .= ' AND action = ?';
            $params[] = $action;
        }

        $statement = $this->pdo->prepare($sql . ' ORDER BY id');
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed> */
    protected function actionPayload(string $actionId, array $ticket, array $overrides = []): array
    {
        return array_replace_recursive([
            'type' => 'block_actions',
            'api_app_id' => TestDatabase::APP_ID,
            'team' => ['id' => TestDatabase::TEAM_ID, 'domain' => 'example'],
            'user' => ['id' => TestDatabase::STAFF, 'team_id' => TestDatabase::TEAM_ID],
            'container' => [
                'type' => 'message',
                'channel_id' => $ticket['slack_message_channel_id'],
                'message_ts' => $ticket['slack_message_ts'],
            ],
            'channel' => ['id' => $ticket['slack_message_channel_id']],
            'response_url' => 'https://hooks.slack.com/actions/T0TEAM0001/1/abc',
            'actions' => [[
                'type' => 'button',
                'block_id' => 'support_ticket_actions',
                'action_id' => $actionId,
                'value' => (string) $ticket['id'],
                'action_ts' => '1768471200.' . str_pad((string) ++self::$actionCounter, 6, '0', STR_PAD_LEFT),
            ]],
        ], $overrides);
    }

    /** @param array<string, mixed>|string $payload */
    protected function slackRequest(array|string $payload, ?int $timestamp = null, string $secret = TestDatabase::SIGNING_SECRET, ?string $signature = null): HttpRequest
    {
        $body = is_string($payload) ? $payload : http_build_query(['payload' => json_encode($payload)]);
        $timestamp ??= $this->clock->now()->getTimestamp();

        return new HttpRequest('POST', [
            'content-type' => 'application/x-www-form-urlencoded',
            'x-slack-request-timestamp' => (string) $timestamp,
            'x-slack-signature' => $signature ?? SlackSignatureVerifier::sign($secret, $timestamp, $body),
        ], $body);
    }

    protected function clickButton(string $actionId, array $ticket, array $overrides = []): HttpResponse
    {
        $response = $this->app->slackActionsEndpoint()->handle($this->slackRequest($this->actionPayload($actionId, $ticket, $overrides)));
        $this->runDeferred($response);

        return $response;
    }

    protected static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    protected function allLogs(): string
    {
        return implode("\n", $this->logs);
    }
}
