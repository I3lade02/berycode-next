<?php

declare(strict_types=1);

namespace BeryCode\Support\Slack;

/**
 * Minimal server-only Slack Web API client (single workspace, bot token).
 * The token never leaves this class and is never logged.
 */
final class SlackClient
{
    /** @param list<string> $allowedResponseUrlPrefixes */
    public function __construct(
        private SlackTransport $transport,
        private ?string $botToken,
        private string $apiBaseUrl,
        private int $timeoutSeconds,
        private array $allowedResponseUrlPrefixes = ['https://hooks.slack.com/'],
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->botToken !== null && $this->botToken !== '';
    }

    /** @param array<string, mixed> $payload */
    public function call(string $method, array $payload): SlackResponse
    {
        if (!$this->isConfigured()) {
            return SlackResponse::localFailure('config_missing_bot_token');
        }

        if (!preg_match('/^[a-zA-Z.]+$/', $method)) {
            throw new \InvalidArgumentException('Invalid Slack method name.');
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        if ($body === false) {
            return SlackResponse::localFailure('payload_encoding_failed');
        }

        return SlackResponse::fromTransport($this->transport->post(
            $this->apiBaseUrl . $method,
            [
                'Authorization' => 'Bearer ' . $this->botToken,
                'Content-Type' => 'application/json; charset=utf-8',
            ],
            $body,
            $this->timeoutSeconds,
        ));
    }

    /**
     * Posts to an interaction's response_url (used for ephemeral feedback to the
     * clicking user). The URL comes from a signed payload but is still checked.
     *
     * @param array<string, mixed> $payload
     */
    public function respond(string $responseUrl, array $payload): SlackResponse
    {
        $allowed = false;

        foreach ($this->allowedResponseUrlPrefixes as $prefix) {
            if (str_starts_with($responseUrl, $prefix)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            return SlackResponse::localFailure('response_url_not_allowed');
        }

        $result = $this->transport->post(
            $responseUrl,
            ['Content-Type' => 'application/json; charset=utf-8'],
            (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            min(3, $this->timeoutSeconds),
        );

        // response_url answers with plain "ok" rather than JSON.
        if ($result->networkError === null && $result->status === 200) {
            return SlackResponse::fromTransport(TransportResult::json(['ok' => true]));
        }

        return SlackResponse::fromTransport($result);
    }
}
