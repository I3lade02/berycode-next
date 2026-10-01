<?php

declare(strict_types=1);

namespace BeryCode\Support\Endpoints;

use BeryCode\Support\App;
use BeryCode\Support\Http\ClientIp;
use BeryCode\Support\Http\HttpRequest;
use BeryCode\Support\Http\HttpResponse;
use BeryCode\Support\Time;

/**
 * /api/support/cron.php — runs due Slack deliveries and message syncs.
 *
 * Over HTTP it requires SUPPORT_CRON_SECRET, sent as "Authorization: Bearer …",
 * as an "X-Support-Cron-Secret" header, or (for schedulers that can only call a
 * plain URL, such as Endora's cron) as ?key=…. Optionally restricted to
 * SUPPORT_CRON_ALLOWED_IPS. HEAD answers 200 without running anything (existence
 * probes). From the command line (php cron.php) no secret is needed.
 */
final class CronEndpoint
{
    private const BATCH_SIZE = 25;

    public function __construct(private App $app)
    {
    }

    public function handle(HttpRequest $request): HttpResponse
    {
        $config = $this->app->config;
        $logger = $this->app->logger();

        if (!$request->cli) {
            if ($request->method === 'HEAD') {
                // Schedulers may check that the script exists with a HEAD request
                // before saving a job and reject anything but 200. A HEAD runs
                // nothing and reveals no more than a 401 would, so it needs no key.
                $logger->info('cron_probe', ['method' => 'HEAD']);

                return HttpResponse::json(200, ['ok' => true]);
            }

            if (!in_array($request->method, ['GET', 'POST'], true)) {
                $logger->warning('cron_rejected', ['reason' => 'method_not_allowed', 'method' => $request->method]);

                return HttpResponse::json(405, ['ok' => false, 'error' => 'method_not_allowed']);
            }

            $config->requireKeys(['SUPPORT_CRON_SECRET']);
            $provided = $this->providedSecret($request);

            if ($provided === null || !hash_equals((string) $config->get('SUPPORT_CRON_SECRET'), $provided)) {
                $logger->warning('cron_rejected', ['reason' => 'bad_secret']);

                return HttpResponse::json(401, ['ok' => false, 'error' => 'unauthorized']);
            }

            $allowedIps = $config->cronAllowedIps();

            if ($allowedIps !== [] && !in_array(ClientIp::resolve($request, $config->get('SUPPORT_CLIENT_IP_HEADER')), $allowedIps, true)) {
                $logger->warning('cron_rejected', ['reason' => 'ip_not_allowed']);

                return HttpResponse::json(403, ['ok' => false, 'error' => 'forbidden']);
            }
        }

        $budget = $config->int('SUPPORT_CRON_TIME_BUDGET_SECONDS', 20, 1, 300);
        @set_time_limit($budget + 30);

        $summary = $this->app->delivery()->runDue(self::BATCH_SIZE, microtime(true) + $budget);
        $summary['rate_limit_rows_purged'] = $this->app->rateLimiter()->purgeExpired();
        $summary['interactions_purged'] = $this->app->tickets()->purgeInteractionsBefore(
            Time::db(Time::plusSeconds($this->app->clock()->now(), -7 * 86400)),
        );

        $logger->info('cron_run', $summary);
        $body = ['ok' => true] + $summary;

        if (!$request->cli) {
            // Setup aid: open the cron URL in a browser and compare with your own
            // public IP. A private address here means SUPPORT_CLIENT_IP_HEADER is needed.
            $body['request_ip'] = ClientIp::resolve($request, $config->get('SUPPORT_CLIENT_IP_HEADER'));
        }

        return HttpResponse::json(200, $body);
    }

    private function providedSecret(HttpRequest $request): ?string
    {
        $authorization = $request->header('authorization');

        if ($authorization !== null && preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches)) {
            return $matches[1];
        }

        $header = $request->header('x-support-cron-secret');

        if ($header !== null && $header !== '') {
            return $header;
        }

        $query = $request->query['key'] ?? null;

        return is_string($query) && $query !== '' ? $query : null;
    }
}
