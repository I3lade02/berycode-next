<?php

declare(strict_types=1);

namespace BeryCode\Support\Slack;

/**
 * Explicit, opt-in development stand-in for Slack (SUPPORT_SLACK_MODE=mock).
 * Config refuses mock mode in production. Payloads are appended to a local log
 * file so the rendered Block Kit can be inspected (e.g. in Block Kit Builder).
 */
final class MockTransport implements SlackTransport
{
    private static int $counter = 0;

    public function __construct(private string $logFile)
    {
    }

    public function post(string $url, array $headers, string $body, int $timeoutSeconds): TransportResult
    {
        $payload = json_decode($body, true);
        $method = basename((string) parse_url($url, PHP_URL_PATH));
        @file_put_contents($this->logFile, json_encode(['method' => $method, 'payload' => $payload], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

        $channel = is_array($payload) ? (string) ($payload['channel'] ?? '') : '';
        $ts = is_array($payload) && isset($payload['ts'])
            ? (string) $payload['ts']
            : sprintf('%d.%06d', time(), ++self::$counter % 1_000_000);

        return TransportResult::json(['ok' => true, 'channel' => $channel, 'ts' => $ts, 'mock' => true]);
    }
}
