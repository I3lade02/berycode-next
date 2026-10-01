<?php

declare(strict_types=1);

namespace BeryCode\Support\Tests\Http;

use BeryCode\Support\App;
use BeryCode\Support\Config;
use BeryCode\Support\Logger;
use BeryCode\Support\Projects\ProjectConfigSync;
use BeryCode\Support\Slack\SlackSignatureVerifier;
use BeryCode\Support\SystemClock;
use BeryCode\Support\Tests\Support\TestCase;
use BeryCode\Support\Tests\Support\TestDatabase;
use PDO;

/**
 * Real HTTP round trips: the PHP entrypoints run under `php -S` (4 workers)
 * through backend/dev/router.php, Slack is a local fake server. Covers the
 * deferred first delivery, the private directory rule and concurrent signed
 * button clicks.
 */
final class HttpEndToEndTest extends TestCase
{
    public const NEEDS_DATABASE = true;

    /** @var list<resource> */
    private array $processes = [];
    private string $apiBase = '';
    private string $fakeBase = '';
    private string $fakeLog = '';
    private PDO $pdo;
    private int $actionCounter = 0;

    public function setUp(): void
    {
        if (!function_exists('proc_open') || !function_exists('curl_multi_init')) {
            $this->skip('proc_open and curl are required');
        }

        $this->pdo = TestDatabase::pdo();
        TestDatabase::truncate($this->pdo);
        $config = Config::fromArray(TestDatabase::baseConfig());
        $app = new App($config, new SystemClock(), new Logger(static function (): void {
        }), $this->pdo);
        $json = json_decode((string) file_get_contents(dirname(__DIR__) . '/fixtures/projects.json'), true);
        $app->projectConfigSync()->apply(ProjectConfigSync::parse($json));

        $this->fakeLog = tempnam(sys_get_temp_dir(), 'fake-slack-') ?: '';
        $fakePort = $this->freePort();
        $apiPort = $this->freePort();
        $this->fakeBase = 'http://127.0.0.1:' . $fakePort;
        $this->apiBase = 'http://127.0.0.1:' . $apiPort;
        $backend = dirname(__DIR__, 2);

        $this->start([PHP_BINARY, '-S', '127.0.0.1:' . $fakePort, $backend . '/tests/fixtures/fake-slack.php'], ['FAKE_SLACK_LOG' => $this->fakeLog], $fakePort);
        $this->start(
            [PHP_BINARY, '-S', '127.0.0.1:' . $apiPort, '-t', $backend . '/web', $backend . '/dev/router.php'],
            TestDatabase::baseConfig() + [
                'SUPPORT_SKIP_DOTENV' => '1',
                'SUPPORT_SLACK_API_BASE_URL' => $this->fakeBase . '/api/',
                'SUPPORT_RATE_LIMIT_IP_PER_10_MIN' => '1000',
                'PHP_CLI_SERVER_WORKERS' => '4',
            ],
            $apiPort,
        );
    }

    public function tearDown(): void
    {
        foreach ($this->processes as $process) {
            proc_terminate($process);
            proc_close($process);
        }

        if ($this->fakeLog !== '' && is_file($this->fakeLog)) {
            unlink($this->fakeLog);
        }
    }

    public function testTicketIsSavedAnsweredAndThenDeliveredToSlack(): void
    {
        [$status, $body] = $this->post('/api/support/tickets.php', (string) json_encode($this->ticketPayload()), ['Content-Type: application/json']);

        $this->assertSame(201, $status);
        $this->assertSame('BC-000001', $body['reference']);

        $this->waitUntil(fn () => $this->ticketRow(1)['delivery_state'] === 'DELIVERED', 'first delivery after the response');
        $posts = $this->fakeCalls('/api/chat.postMessage');
        $this->assertCount(3, $posts, 'ticket message + one thread reply per issue');
        $this->assertSame('Bearer xoxb-test-token-not-real', $posts[0]['authorization']);
        $this->assertSame('C0ACME0001', $posts[0]['payload']['channel']);
        $this->assertFalse(isset($posts[0]['payload']['thread_ts']));
        $this->assertSame($this->ticketRow(1)['slack_message_ts'], $posts[1]['payload']['thread_ts']);
        $this->assertSame($this->ticketRow(1)['slack_message_ts'], $posts[2]['payload']['thread_ts']);
        $this->assertNotNull($this->ticketRow(1)['slack_message_ts'], 'message ts stored');

        [$status, $body] = $this->get('/api/support/projects.php');
        $this->assertSame(200, $status);
        $this->assertSame(['acme-web', 'nord-shop'], array_column($body['projects'], 'code'));

        [$status] = $this->get('/api/support/_app/bootstrap.php');
        $this->assertSame(404, $status, 'private directory is not served');
        [$status] = $this->get('/api/support/cron.php');
        $this->assertSame(401, $status);
        [$status, $body] = $this->get('/api/support/cron.php?key=' . TestDatabase::CRON_SECRET);
        $this->assertSame(200, $status);
        $this->assertTrue($body['ok']);
    }

    public function testConcurrentButtonClicksKeepTheTicketConsistent(): void
    {
        $this->post('/api/support/tickets.php', (string) json_encode($this->ticketPayload()), ['Content-Type: application/json']);
        $this->waitUntil(fn () => $this->ticketRow(1)['delivery_state'] === 'DELIVERED', 'delivery');
        $ticket = $this->ticketRow(1);

        $clicks = [
            ['support_assign_me', TestDatabase::STAFF],
            ['support_assign_me', TestDatabase::OTHER_STAFF],
            ['support_start', TestDatabase::STAFF],
            ['support_resolve', TestDatabase::OTHER_STAFF],
            ['support_start', TestDatabase::OTHER_STAFF],
            ['support_reopen', TestDatabase::STAFF],
            ['support_resolve', TestDatabase::STAFF],
            ['support_assign_me', TestDatabase::STAFF],
        ];
        $requests = [];

        foreach ($clicks as [$action, $user]) {
            $requests[] = $this->signedAction($action, $user, $ticket);
        }

        // One payload replayed three times at once.
        $replay = $this->signedAction('support_resolve', TestDatabase::STAFF, $ticket);
        array_push($requests, $replay, $replay, $replay);

        foreach ($this->parallelPost('/api/support/slack-actions.php', $requests) as $status) {
            $this->assertSame(200, $status, 'every interaction acknowledged');
        }

        $this->waitUntil(function (): bool {
            $row = $this->ticketRow(1);

            return $row['sync_state'] === 'IDLE' && (int) $row['slack_synced_version'] === (int) $row['version'];
        }, 'Slack message converges on the final state');

        $row = $this->ticketRow(1);
        $outcomes = $this->pdo->query('SELECT outcome, COUNT(*) AS n FROM support_slack_interactions GROUP BY outcome')->fetchAll(PDO::FETCH_KEY_PAIR);
        $changed = (int) ($outcomes['changed'] ?? 0);

        $this->assertSame(9, array_sum(array_map('intval', $outcomes)), 'replayed payload recorded once');
        $this->assertFalse(isset($outcomes['processing']), 'no half-finished interactions');
        $this->assertSame(1 + $changed, (int) $row['version'], 'version counts exactly the applied changes');

        $status = 'NEW';
        $assignee = null;

        foreach ($this->pdo->query('SELECT * FROM support_ticket_events WHERE ticket_id = 1 ORDER BY id')->fetchAll() as $event) {
            if ($event['action'] === 'STATUS_CHANGED') {
                $this->assertSame($status, $event['from_status'], 'status history is a consistent chain');
                $status = $event['to_status'];
            }

            if ($event['action'] === 'ASSIGNED') {
                $this->assertSame($assignee, $event['from_assignee'], 'assignment history is a consistent chain');
                $assignee = $event['to_assignee'];
            }
        }

        $this->assertSame($row['status'], $status);
        $this->assertSame($row['assignee_slack_user_id'], $assignee);

        $updates = $this->fakeCalls('/api/chat.update');
        $this->assertTrue($updates !== []);
        $lastBlocks = (string) json_encode(end($updates)['payload']['blocks']);
        $label = ['NEW' => 'New', 'IN_PROGRESS' => 'In progress', 'RESOLVED' => 'Resolved'][$row['status']];
        $this->assertStringContainsString($label, $lastBlocks, 'last chat.update shows the final status');
    }

    // ------------------------------------------------------------------ helpers

    /** @return array<string, mixed> */
    private function ticketPayload(): array
    {
        return [
            'name' => 'Http Test',
            'email' => 'http@example.com',
            'project' => 'Acme Web Studio',
            'issues' => [
                ['requestType' => 'bug', 'priority' => 'normal', 'subject' => 'End-to-end request', 'description' => 'This request travels through the real PHP entrypoint.'],
                ['requestType' => 'change_request', 'priority' => 'high', 'subject' => 'Second issue in the same ticket', 'description' => 'Its details must arrive as a reply in the Slack thread.'],
            ],
            'locale' => 'en',
            'idempotencyKey' => bin2hex(random_bytes(16)),
            'website' => '',
        ];
    }

    private function signedAction(string $action, string $user, array $ticket): array
    {
        $payload = [
            'type' => 'block_actions',
            'api_app_id' => TestDatabase::APP_ID,
            'team' => ['id' => TestDatabase::TEAM_ID],
            'user' => ['id' => $user, 'team_id' => TestDatabase::TEAM_ID],
            'container' => ['type' => 'message', 'channel_id' => $ticket['slack_message_channel_id'], 'message_ts' => $ticket['slack_message_ts']],
            'response_url' => $this->fakeBase . '/api/response/' . $this->actionCounter,
            'actions' => [[
                'action_id' => $action,
                'value' => (string) $ticket['id'],
                'action_ts' => sprintf('%d.%06d', time(), ++$this->actionCounter),
            ]],
        ];
        $body = http_build_query(['payload' => json_encode($payload)]);
        $timestamp = time();

        return [
            'body' => $body,
            'headers' => [
                'Content-Type: application/x-www-form-urlencoded',
                'X-Slack-Request-Timestamp: ' . $timestamp,
                'X-Slack-Signature: ' . SlackSignatureVerifier::sign(TestDatabase::SIGNING_SECRET, $timestamp, $body),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function ticketRow(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM support_tickets WHERE id = ?');
        $statement->execute([$id]);

        return $statement->fetch() ?: ['delivery_state' => null, 'sync_state' => null, 'version' => 0, 'slack_synced_version' => -1];
    }

    /** @return list<array<string, mixed>> */
    private function fakeCalls(string $path): array
    {
        $calls = [];

        foreach (file($this->fakeLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $entry = json_decode($line, true);

            if (is_array($entry) && $entry['path'] === $path) {
                $calls[] = $entry;
            }
        }

        return $calls;
    }

    private function waitUntil(callable $condition, string $what, float $timeout = 10.0): void
    {
        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            if ($condition()) {
                $this->assertTrue(true);

                return;
            }

            usleep(50_000);
        }

        $this->assertTrue(false, 'timed out waiting for: ' . $what);
    }

    /** @return array{0: int, 1: mixed} */
    private function post(string $path, string $body, array $headers): array
    {
        return $this->request('POST', $path, $body, $headers);
    }

    /** @return array{0: int, 1: mixed} */
    private function get(string $path): array
    {
        return $this->request('GET', $path, null, []);
    }

    /** @return array{0: int, 1: mixed} */
    private function request(string $method, string $path, ?string $body, array $headers): array
    {
        $handle = curl_init($this->apiBase . $path);
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $response = (string) curl_exec($handle);

        return [(int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), json_decode($response, true)];
    }

    /**
     * @param list<array{body: string, headers: list<string>}> $requests
     * @return list<int> status codes
     */
    private function parallelPost(string $path, array $requests): array
    {
        $multi = curl_multi_init();
        $handles = [];

        foreach ($requests as $request) {
            $handle = curl_init($this->apiBase . $path);
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $request['body'],
                CURLOPT_HTTPHEADER => $request['headers'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            curl_multi_add_handle($multi, $handle);
            $handles[] = $handle;
        }

        do {
            $status = curl_multi_exec($multi, $running);

            if ($running) {
                curl_multi_select($multi, 0.1);
            }
        } while ($running && $status === CURLM_OK);

        $codes = [];

        foreach ($handles as $handle) {
            $codes[] = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_multi_remove_handle($multi, $handle);
        }

        return $codes;
    }

    private function freePort(): int
    {
        for ($i = 0; $i < 50; $i++) {
            $port = random_int(20000, 45000);
            $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1);

            if ($socket === false) {
                return $port;
            }

            fclose($socket);
        }

        throw new \RuntimeException('No free port found.');
    }

    /** @param array<string, string> $env */
    private function start(array $command, array $env, int $port): void
    {
        $env += ['PATH' => (string) getenv('PATH'), 'HOME' => (string) getenv('HOME')];
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, $env);

        if (!is_resource($process)) {
            throw new \RuntimeException('Could not start ' . $command[0]);
        }

        $this->processes[] = $process;

        for ($i = 0; $i < 100; $i++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1);

            if ($socket !== false) {
                fclose($socket);

                return;
            }

            usleep(50_000);
        }

        throw new \RuntimeException('Server on port ' . $port . ' did not start.');
    }
}
