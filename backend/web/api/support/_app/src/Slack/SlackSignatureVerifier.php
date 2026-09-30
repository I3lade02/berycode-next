<?php

declare(strict_types=1);

namespace BeryCode\Support\Slack;

use BeryCode\Support\Clock;

/**
 * Verifies X-Slack-Signature over the raw request body, before anything is
 * parsed: https://docs.slack.dev/authentication/verifying-requests-from-slack/
 */
final class SlackSignatureVerifier
{
    public const OK = 'ok';

    public function __construct(
        private string $signingSecret,
        private Clock $clock,
        private int $toleranceSeconds = 300,
    ) {
    }

    /** Returns self::OK or a short reason code. */
    public function verify(?string $timestampHeader, ?string $signatureHeader, string $rawBody): string
    {
        if ($this->signingSecret === '') {
            return 'not_configured';
        }

        if ($timestampHeader === null || !preg_match('/^\d{1,12}$/', $timestampHeader)) {
            return 'missing_timestamp';
        }

        if (abs($this->clock->now()->getTimestamp() - (int) $timestampHeader) > $this->toleranceSeconds) {
            return 'stale_timestamp';
        }

        if ($signatureHeader === null || !preg_match('/^v0=[a-f0-9]{64}$/', $signatureHeader)) {
            return 'missing_signature';
        }

        $expected = 'v0=' . hash_hmac('sha256', 'v0:' . $timestampHeader . ':' . $rawBody, $this->signingSecret);

        return hash_equals($expected, $signatureHeader) ? self::OK : 'bad_signature';
    }

    /** Used by tests and local tooling to produce valid signatures. */
    public static function sign(string $signingSecret, int $timestamp, string $rawBody): string
    {
        return 'v0=' . hash_hmac('sha256', 'v0:' . $timestamp . ':' . $rawBody, $signingSecret);
    }
}
