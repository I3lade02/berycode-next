<?php

declare(strict_types=1);

namespace BeryCode\Support\Slack;

/**
 * Outcome of one Slack Web API call. Success requires HTTP 200 *and* "ok": true.
 * Failures are classified as retryable (transient) or permanent
 * (configuration/permission problems that retrying cannot fix).
 */
final class SlackResponse
{
    /**
     * Errors that need a human to fix configuration or permissions.
     * https://docs.slack.dev/reference/methods/chat.postMessage/ and chat.update
     */
    private const PERMANENT_ERRORS = [
        'access_denied', 'account_inactive', 'as_user_not_supported', 'cannot_reply_to_message',
        'cant_update_message', 'channel_not_found', 'channel_type_not_supported', 'duplicate_channel_not_found',
        'edit_window_closed', 'ekm_access_denied', 'enterprise_is_restricted', 'invalid_arg_name', 'invalid_arguments',
        'invalid_array_arg', 'invalid_auth', 'invalid_blocks', 'invalid_blocks_format', 'invalid_charset',
        'invalid_form_data', 'invalid_metadata_format', 'invalid_metadata_schema', 'invalid_post_type',
        'is_archived', 'message_not_found', 'messages_tab_disabled', 'metadata_must_be_sent_from_app',
        'metadata_too_large', 'method_deprecated', 'missing_post_type', 'missing_scope', 'msg_blocks_too_long',
        'msg_too_long', 'no_permission', 'no_text', 'not_allowed_token_type', 'not_authed', 'not_in_channel',
        'org_login_required', 'restricted_action', 'restricted_action_non_threadable_channel',
        'restricted_action_read_only_channel', 'restricted_action_thread_only_channel', 'team_access_not_granted',
        'token_expired', 'token_revoked', 'too_many_attachments', 'two_factor_setup_required', 'update_failed',
    ];

    private const MAX_RETRY_AFTER_SECONDS = 3600;

    /** @param array<string, mixed> $data */
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $error,
        public readonly bool $retryable,
        public readonly bool $ambiguous,
        public readonly ?int $retryAfterSeconds,
        public readonly ?int $httpStatus,
        public readonly array $data,
    ) {
    }

    public static function fromTransport(TransportResult $result): self
    {
        if ($result->networkError !== null) {
            return new self(false, $result->networkError, true, $result->requestSent, null, null, []);
        }

        $status = $result->status ?? 0;
        $retryAfter = self::retryAfter($result->headers['retry-after'] ?? null);

        if ($status === 429) {
            return new self(false, 'ratelimited', true, false, $retryAfter, $status, []);
        }

        if ($status >= 500) {
            // Slack may or may not have processed the request.
            return new self(false, 'http_' . $status, true, true, $retryAfter, $status, []);
        }

        if ($status !== 200) {
            return new self(false, 'http_' . $status, false, false, null, $status, []);
        }

        $data = json_decode($result->body, true);

        if (!is_array($data) || !array_key_exists('ok', $data)) {
            return new self(false, 'invalid_response', true, true, null, $status, []);
        }

        if ($data['ok'] === true) {
            return new self(true, null, false, false, null, $status, $data);
        }

        $error = is_string($data['error'] ?? null) && preg_match('/^[a-z0-9_]{1,64}$/', $data['error'])
            ? $data['error']
            : 'unknown_error';

        if ($error === 'ratelimited' || $error === 'rate_limited') {
            return new self(false, 'ratelimited', true, false, $retryAfter, $status, []);
        }

        // Unknown errors are retried, but only up to the bounded attempt limit.
        $permanent = in_array($error, self::PERMANENT_ERRORS, true);

        return new self(false, $error, !$permanent, false, null, $status, []);
    }

    public static function localFailure(string $code, bool $retryable = false): self
    {
        return new self(false, $code, $retryable, false, null, null, []);
    }

    /** Sanitized description suitable for the database and logs. */
    public function errorSummary(): string
    {
        $summary = 'slack:' . ($this->error ?? 'unknown_error');

        if ($this->ambiguous) {
            $summary .= ' (ambiguous: request may have reached Slack)';
        }

        return $summary;
    }

    private static function retryAfter(?string $header): ?int
    {
        if ($header === null || !preg_match('/^\d{1,6}$/', trim($header))) {
            return null;
        }

        return max(1, min(self::MAX_RETRY_AFTER_SECONDS, (int) trim($header)));
    }
}
