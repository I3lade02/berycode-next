<?php

declare(strict_types=1);

namespace BeryCode\Support\Tests\Support;

use BeryCode\Support\Slack\SlackTransport;
use BeryCode\Support\Slack\TransportResult;

/**
 * Scripted Slack for tests. Queued responses are used in order; when the queue
 * is empty a successful response is synthesized. Every request is recorded.
 */
final class FakeTransport implements SlackTransport
{
    /** @var list<array{url: string, method: string, headers: array<string, string>, payload: mixed}> */
    public array $requests = [];

    /** @var list<TransportResult|callable(string, array<mixed>): TransportResult> */
    private array $queue = [];

    private int $counter = 0;

    public function queue(TransportResult|callable ...$responses): self
    {
        foreach ($responses as $response) {
            $this->queue[] = $response;
        }

        return $this;
    }

    public function post(string $url, array $headers, string $body, int $timeoutSeconds): TransportResult
    {
        $payload = json_decode($body, true);
        $method = basename((string) parse_url($url, PHP_URL_PATH));
        $this->requests[] = ['url' => $url, 'method' => $method, 'headers' => $headers, 'payload' => $payload];

        if ($this->queue !== []) {
            $next = array_shift($this->queue);

            return $next instanceof TransportResult ? $next : $next($method, is_array($payload) ? $payload : []);
        }

        if (!str_contains($url, '/api/')) {
            return new TransportResult(200, 'ok');
        }

        return match ($method) {
            'chat.postMessage' => TransportResult::json([
                'ok' => true,
                'channel' => $payload['channel'] ?? 'C0UNKNOWN0',
                'ts' => sprintf('1700000000.%06d', ++$this->counter),
            ]),
            'chat.update' => TransportResult::json(['ok' => true, 'channel' => $payload['channel'] ?? '', 'ts' => $payload['ts'] ?? '']),
            default => TransportResult::json(['ok' => true]),
        };
    }

    /** @return list<array{url: string, method: string, headers: array<string, string>, payload: mixed}> */
    public function calls(string $method): array
    {
        return array_values(array_filter($this->requests, static fn (array $request): bool => $request['method'] === $method));
    }

    /** @return list<array{url: string, method: string, headers: array<string, string>, payload: mixed}> */
    public function responseUrlCalls(): array
    {
        return array_values(array_filter($this->requests, static fn (array $request): bool => str_starts_with($request['url'], 'https://hooks.slack.com/')));
    }
}
