<?php

declare(strict_types=1);

namespace BeryCode\Support\Endpoints;

use BeryCode\Support\App;
use BeryCode\Support\ConfigException;
use BeryCode\Support\Http\HttpRequest;
use BeryCode\Support\Http\HttpResponse;
use BeryCode\Support\Slack\SlackLabels;
use BeryCode\Support\Slack\SlackSignatureVerifier;
use BeryCode\Support\Tickets\StaffAction;
use BeryCode\Support\Tickets\StaffActionRequest;
use BeryCode\Support\Tickets\StaffActionResult;

/**
 * POST /api/support/slack-actions.php — Slack interactivity Request URL.
 *
 * Order of checks: signature over the raw body (before parsing) and timestamp
 * freshness -> payload shape -> workspace (and app) -> staff allowlist ->
 * action id -> ticket/message association and state transition inside a
 * locked transaction. Slack is acknowledged with 200 as soon as the database
 * change commits; chat.update and ephemeral replies run after the response.
 */
final class SlackActionsEndpoint
{
    public const MAX_BODY_BYTES = 256 * 1024;

    public function __construct(private App $app)
    {
    }

    public function handle(HttpRequest $request): HttpResponse
    {
        if ($request->method !== 'POST') {
            return HttpResponse::empty(405);
        }

        if ($request->bodyTooLarge) {
            return HttpResponse::empty(413);
        }

        $config = $this->app->config;
        $logger = $this->app->logger();
        $config->requireKeys(['SLACK_SIGNING_SECRET', 'SLACK_TEAM_ID']);

        $verifier = new SlackSignatureVerifier((string) $config->slackSigningSecret(), $this->app->clock());
        $verdict = $verifier->verify(
            $request->header('x-slack-request-timestamp'),
            $request->header('x-slack-signature'),
            $request->body,
        );

        if ($verdict !== SlackSignatureVerifier::OK) {
            $logger->warning('slack_request_rejected', ['reason' => $verdict]);

            return HttpResponse::empty(401);
        }

        parse_str($request->body, $form);
        $payload = is_string($form['payload'] ?? null) ? json_decode($form['payload'], true, 64) : null;

        if (!is_array($payload)) {
            return HttpResponse::empty(400);
        }

        if (($payload['type'] ?? null) !== 'block_actions') {
            // Nothing else is configured for this app; acknowledge and ignore.
            return HttpResponse::empty(200);
        }

        $teamId = self::stringAt($payload, ['team', 'id']) ?? self::stringAt($payload, ['user', 'team_id']);

        if ($teamId === null || !hash_equals((string) $config->slackTeamId(), $teamId)) {
            $logger->warning('slack_request_rejected', ['reason' => 'wrong_workspace']);

            return HttpResponse::empty(403);
        }

        $appId = $config->slackAppId();

        if ($appId !== null && ($payload['api_app_id'] ?? null) !== $appId) {
            $logger->warning('slack_request_rejected', ['reason' => 'wrong_app']);

            return HttpResponse::empty(403);
        }

        $userId = self::stringAt($payload, ['user', 'id']);

        if ($userId === null || !preg_match('/^[UW][A-Z0-9]{2,31}$/', $userId)) {
            return HttpResponse::empty(400);
        }

        $responseUrl = self::stringAt($payload, ['response_url']);
        $actions = $payload['actions'] ?? null;
        $response = HttpResponse::empty(200);

        try {
            $staff = $config->staffUserIds();
        } catch (ConfigException $exception) {
            $logger->error('configuration_error', ['detail' => $exception->getMessage()]);
            $staff = [];
        }

        // A valid signature proves the request came from Slack, not that the
        // clicking user may manage tickets.
        if (!in_array($userId, $staff, true)) {
            $logger->warning('slack_action_unauthorized', ['user' => $userId]);

            return $this->reply($response, $responseUrl, 'reply_not_authorized');
        }

        if (!is_array($actions) || count($actions) !== 1 || !is_array($actions[0] ?? null)) {
            return $this->reply($response, $responseUrl, 'reply_unsupported');
        }

        $actionData = $actions[0];
        $action = is_string($actionData['action_id'] ?? null) ? StaffAction::tryFrom($actionData['action_id']) : null;
        $value = $actionData['value'] ?? null;

        if ($action === null || !is_string($value) || !preg_match('/^[1-9]\d{0,18}$/', $value)) {
            $logger->warning('slack_action_rejected', ['reason' => 'unsupported_action', 'user' => $userId]);

            return $this->reply($response, $responseUrl, 'reply_unsupported');
        }

        $channelId = self::stringAt($payload, ['container', 'channel_id']) ?? self::stringAt($payload, ['channel', 'id']);
        $messageTs = self::stringAt($payload, ['container', 'message_ts']) ?? self::stringAt($payload, ['message', 'ts']);
        $actionTs = self::stringAt($actionData, ['action_ts']) ?? '';

        if ($channelId === null || $messageTs === null) {
            return $this->reply($response, $responseUrl, 'reply_message_mismatch');
        }

        $ticketId = (int) $value;
        $result = $this->app->ticketService()->applyStaffAction(new StaffActionRequest(
            $ticketId,
            $action,
            $userId,
            $channelId,
            $messageTs,
            hash('sha256', (string) json_encode([$teamId, $userId, $action->value, $value, $channelId, $messageTs, $actionTs])),
        ));

        switch ($result->outcome) {
            case StaffActionResult::CHANGED:
                return $response->defer(fn () => $this->app->delivery()->sync($ticketId));

            case StaffActionResult::DUPLICATE:
                return $response;

            case StaffActionResult::NOOP:
            case StaffActionResult::INVALID:
                // Also retry a pending sync in case the clicked message was stale.
                $response->defer(fn () => $this->app->delivery()->sync($ticketId));

                return $this->reply($response, $responseUrl, 'reply_' . $result->reason, (string) $result->reference);

            case StaffActionResult::MESSAGE_MISMATCH:
                return $this->reply($response, $responseUrl, 'reply_message_mismatch');

            default:
                return $this->reply($response, $responseUrl, 'reply_not_found');
        }
    }

    private function reply(HttpResponse $response, ?string $responseUrl, string $labelKey, string ...$args): HttpResponse
    {
        if ($responseUrl === null) {
            return $response;
        }

        $labels = new SlackLabels($this->app->config->slackLocale());
        $text = $labels->has($labelKey) ? $labels->get($labelKey, ...$args) : $labels->get('reply_unsupported');

        return $response->defer(function () use ($responseUrl, $text): void {
            $this->app->slackClient()->respond($responseUrl, [
                'response_type' => 'ephemeral',
                'replace_original' => false,
                'text' => $text,
            ]);
        });
    }

    /**
     * @param array<mixed> $data
     * @param list<string> $path
     */
    private static function stringAt(array $data, array $path): ?string
    {
        foreach ($path as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return null;
            }

            $data = $data[$segment];
        }

        return is_string($data) && $data !== '' ? $data : null;
    }
}
