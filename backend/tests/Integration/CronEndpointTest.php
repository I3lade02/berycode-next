<?php

declare(strict_types=1);

namespace BeryCode\Support\Tests\Integration;

use BeryCode\Support\Http\HttpRequest;
use BeryCode\Support\Slack\TransportResult;
use BeryCode\Support\Tests\Support\DbTestCase;
use BeryCode\Support\Tests\Support\TestDatabase;

final class CronEndpointTest extends DbTestCase
{
    private function cron(array $headers = [], array $query = [], string $ip = '62.109.128.59', bool $cli = false): \BeryCode\Support\Http\HttpResponse
    {
        return $this->app->cronEndpoint()->handle(new HttpRequest('GET', $headers, '', $query, ['REMOTE_ADDR' => $ip], false, $cli));
    }

    public function testRequiresTheSecretOverHttp(): void
    {
        $this->assertSame(401, $this->cron()->status);
        $this->assertSame(401, $this->cron(['authorization' => 'Bearer wrong'])->status);
        $this->assertSame(401, $this->cron([], ['key' => 'wrong'])->status);

        $this->assertSame(200, $this->cron(['authorization' => 'Bearer ' . TestDatabase::CRON_SECRET])->status);
        $this->assertSame(200, $this->cron(['x-support-cron-secret' => TestDatabase::CRON_SECRET])->status);
        $this->assertSame(200, $this->cron([], ['key' => TestDatabase::CRON_SECRET])->status);
        $this->assertSame(200, $this->cron([], [], '127.0.0.1', true)->status, 'CLI needs no secret');
    }

    public function testOptionalIpAllowlist(): void
    {
        $this->app = $this->makeApp(['SUPPORT_CRON_ALLOWED_IPS' => '62.109.128.59, 212.57.32.9']);

        $this->assertSame(200, $this->cron([], ['key' => TestDatabase::CRON_SECRET], '212.57.32.9')->status);
        $this->assertSame(403, $this->cron([], ['key' => TestDatabase::CRON_SECRET], '198.51.100.7')->status);
    }

    public function testRunsDueDeliveriesAndCleansUp(): void
    {
        $this->slack->queue(TransportResult::networkError('network:dns', false));
        $response = $this->submit($this->validPayload());
        $this->runDeferred($response);
        $this->assertSame('PENDING', $this->ticket('BC-000001')['delivery_state']);

        $this->clock->advance(7200);
        $result = $this->cron([], ['key' => TestDatabase::CRON_SECRET]);

        $this->assertSame(200, $result->status);
        $this->assertSame(1, $result->json['delivered']);
        $this->assertSame(1, $result->json['rate_limit_rows_purged'] >= 1 ? 1 : 0, 'expired rate-limit windows purged');
        $this->assertSame('DELIVERED', $this->ticket('BC-000001')['delivery_state']);
        $this->assertStringNotContainsString('BC-000001', (string) json_encode($result->json), 'summary has counts only');
        $this->assertSame('62.109.128.59', $result->json['request_ip'], 'IP seen by the backend, for proxy setup checks');
    }
}
