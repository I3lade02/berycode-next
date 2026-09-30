<?php

declare(strict_types=1);

namespace BeryCode\Support\Endpoints;

use BeryCode\Support\App;
use BeryCode\Support\Http\ClientIp;
use BeryCode\Support\Http\HttpRequest;
use BeryCode\Support\Http\HttpResponse;
use BeryCode\Support\Projects\ProjectResolution;
use BeryCode\Support\Security\RateLimitDecision;
use BeryCode\Support\Tickets\IdempotencyConflict;
use BeryCode\Support\Tickets\TicketValidator;

/**
 * POST /api/support/tickets.php — the public intake endpoint.
 *
 * Responds only after the ticket is committed. The Slack post happens after the
 * response and is retried by the cron job if it fails. Error bodies contain
 * stable codes only; no internal details, channel IDs or delivery state.
 */
final class TicketEndpoint
{
    /** Room for 10 issues of 5,000 characters each, even in 4-byte UTF-8. */
    public const MAX_BODY_BYTES = 256 * 1024;

    public function __construct(private App $app)
    {
    }

    public function handle(HttpRequest $request): HttpResponse
    {
        if ($request->method !== 'POST') {
            return HttpResponse::json(405, ['ok' => false, 'error' => 'method_not_allowed'], ['Allow' => 'POST']);
        }

        if ($request->bodyTooLarge) {
            return HttpResponse::json(413, ['ok' => false, 'error' => 'payload_too_large']);
        }

        if ($request->contentType() !== 'application/json') {
            return HttpResponse::json(415, ['ok' => false, 'error' => 'unsupported_media_type']);
        }

        $config = $this->app->config;
        $logger = $this->app->logger();
        $origin = $request->header('origin');
        $allowedOrigins = $config->allowedOrigins();

        if ($origin !== null && $allowedOrigins !== [] && !in_array(rtrim(strtolower($origin), '/'), $allowedOrigins, true)) {
            $logger->warning('ticket_rejected_origin');

            return HttpResponse::json(403, ['ok' => false, 'error' => 'forbidden']);
        }

        $limiter = $this->app->rateLimiter();
        $ip = ClientIp::resolve($request, $config->get('SUPPORT_CLIENT_IP_HEADER'));

        foreach ([
            ['ip_10m', 600, $config->int('SUPPORT_RATE_LIMIT_IP_PER_10_MIN', 10, 1, 10000)],
            ['ip_day', 86400, $config->int('SUPPORT_RATE_LIMIT_IP_PER_DAY', 40, 1, 100000)],
        ] as [$scope, $window, $limit]) {
            $decision = $limiter->hit($scope, $ip, $limit, $window);

            if (!$decision->allowed) {
                return $this->rateLimited($scope, $decision);
            }
        }

        $data = json_decode($request->body, true, 16);

        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            return HttpResponse::json(400, ['ok' => false, 'error' => 'invalid_request']);
        }

        // Honeypot: a visually hidden field real visitors never fill in.
        $honeypot = $data['website'] ?? '';

        if (!is_string($honeypot) || trim($honeypot) !== '') {
            $logger->info('ticket_rejected_honeypot');

            return HttpResponse::json(422, ['ok' => false, 'error' => 'rejected']);
        }

        [$submission, $errors] = TicketValidator::validate($data);

        if ($submission === null) {
            $logger->info('ticket_validation_failed', ['fields' => implode(',', array_keys($errors))]);

            return HttpResponse::json(422, ['ok' => false, 'error' => 'validation_failed', 'fields' => $errors]);
        }

        $service = $this->app->ticketService();

        try {
            $existing = $service->findExisting($submission);
        } catch (IdempotencyConflict) {
            return HttpResponse::json(409, ['ok' => false, 'error' => 'idempotency_conflict']);
        }

        if ($existing !== null) {
            // Browser retry of a request we already saved: same answer, no new ticket.
            return $this->withDeliveryAttempt(
                HttpResponse::json(200, ['ok' => true, 'reference' => $existing->reference, 'duplicate' => true]),
                $existing->id,
            );
        }

        $resolution = $this->app->projectResolver()->resolve($submission->projectInput);

        if (!$resolution->isFound() || $resolution->project === null) {
            $body = ['ok' => false, 'error' => 'validation_failed', 'fields' => ['project' => $resolution->status]];

            if ($resolution->status === ProjectResolution::NOT_FOUND && $resolution->suggestions !== []) {
                $body['suggestions'] = $resolution->suggestions;
            }

            $logger->info('ticket_project_rejected', ['reason' => $resolution->status]);

            return HttpResponse::json(422, $body);
        }

        foreach ([
            ['email_hour', strtolower($submission->customerEmail), $config->int('SUPPORT_RATE_LIMIT_EMAIL_PER_HOUR', 5, 1, 10000)],
            ['global_hour', 'all', $config->int('SUPPORT_RATE_LIMIT_GLOBAL_PER_HOUR', 100, 1, 100000)],
        ] as [$scope, $subject, $limit]) {
            $decision = $limiter->hit($scope, $subject, $limit, 3600);

            if (!$decision->allowed) {
                return $this->rateLimited($scope, $decision);
            }
        }

        try {
            $created = $service->create($submission, $resolution->project);
        } catch (IdempotencyConflict) {
            return HttpResponse::json(409, ['ok' => false, 'error' => 'idempotency_conflict']);
        }

        $logger->info('ticket_created', [
            'ticket' => $created->reference,
            'project' => $resolution->project->code,
            'issues' => count($submission->issues),
            'duplicate' => $created->duplicate,
        ]);

        return $this->withDeliveryAttempt(
            HttpResponse::json($created->duplicate ? 200 : 201, [
                'ok' => true,
                'reference' => $created->reference,
                'duplicate' => $created->duplicate,
            ]),
            $created->id,
        );
    }

    private function withDeliveryAttempt(HttpResponse $response, int $ticketId): HttpResponse
    {
        // Runs after the customer has the response. If it fails or the process is
        // killed, the ticket stays PENDING (or its lease expires) for the cron job.
        return $response->defer(fn () => $this->app->delivery()->deliver($ticketId));
    }

    private function rateLimited(string $scope, RateLimitDecision $decision): HttpResponse
    {
        $this->app->logger()->warning('ticket_rate_limited', ['scope' => $scope]);

        return HttpResponse::json(
            429,
            ['ok' => false, 'error' => 'rate_limited', 'retryAfter' => $decision->retryAfterSeconds],
            ['Retry-After' => (string) $decision->retryAfterSeconds],
        );
    }
}
