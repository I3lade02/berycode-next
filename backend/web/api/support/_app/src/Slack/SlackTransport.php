<?php

declare(strict_types=1);

namespace BeryCode\Support\Slack;

/** Sends one HTTPS POST. Implemented with cURL in production and faked in tests. */
interface SlackTransport
{
    /** @param array<string, string> $headers */
    public function post(string $url, array $headers, string $body, int $timeoutSeconds): TransportResult;
}
