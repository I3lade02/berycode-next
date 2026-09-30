<?php

declare(strict_types=1);

namespace BeryCode\Support\Tests\Unit;

use BeryCode\Support\Delivery\RetryPolicy;
use BeryCode\Support\Slack\SlackResponse;
use BeryCode\Support\Slack\TransportResult;
use BeryCode\Support\Tests\Support\TestCase;

final class SlackResponseTest extends TestCase
{
    public function testHttp200AloneIsNotSuccess(): void
    {
        $response = SlackResponse::fromTransport(TransportResult::json(['ok' => false, 'error' => 'not_in_channel']));
        $this->assertFalse($response->ok);
        $this->assertFalse($response->retryable, 'permission problems are permanent');
        $this->assertSame('slack:not_in_channel', $response->errorSummary());

        $this->assertTrue(SlackResponse::fromTransport(TransportResult::json(['ok' => true, 'ts' => '1.2']))->ok);
        $this->assertFalse(SlackResponse::fromTransport(new TransportResult(200, 'not json'))->ok);
        $this->assertFalse(SlackResponse::fromTransport(TransportResult::json(['channel' => 'C1']))->ok, 'missing ok flag');
    }

    public function testClassifiesRetryableFailures(): void
    {
        $rateLimited = SlackResponse::fromTransport(new TransportResult(429, '', ['retry-after' => '42']));
        $this->assertTrue($rateLimited->retryable);
        $this->assertSame(42, $rateLimited->retryAfterSeconds);
        $this->assertSame('ratelimited', $rateLimited->error);

        $serverError = SlackResponse::fromTransport(new TransportResult(503, 'down'));
        $this->assertTrue($serverError->retryable);
        $this->assertTrue($serverError->ambiguous);

        $timeout = SlackResponse::fromTransport(TransportResult::networkError('network:timeout', true));
        $this->assertTrue($timeout->retryable);
        $this->assertTrue($timeout->ambiguous);
        $this->assertStringContainsString('ambiguous', $timeout->errorSummary());

        $dns = SlackResponse::fromTransport(TransportResult::networkError('network:dns', false));
        $this->assertTrue($dns->retryable);
        $this->assertFalse($dns->ambiguous);

        $this->assertTrue(SlackResponse::fromTransport(TransportResult::json(['ok' => false, 'error' => 'internal_error']))->retryable);
        $this->assertTrue(SlackResponse::fromTransport(TransportResult::json(['ok' => false, 'error' => 'some_new_error']))->retryable, 'unknown errors retried (bounded)');
    }

    public function testClassifiesPermanentFailuresAndSanitizesErrorCodes(): void
    {
        foreach (['channel_not_found', 'is_archived', 'invalid_auth', 'token_revoked', 'missing_scope', 'invalid_blocks', 'message_not_found'] as $error) {
            $this->assertFalse(SlackResponse::fromTransport(TransportResult::json(['ok' => false, 'error' => $error]))->retryable, $error);
        }

        $this->assertFalse(SlackResponse::fromTransport(new TransportResult(404, ''))->retryable);

        $weird = SlackResponse::fromTransport(TransportResult::json(['ok' => false, 'error' => "<script>alert(1)</script>\nxoxb-secret"]));
        $this->assertSame('unknown_error', $weird->error);
    }

    public function testRetryAfterIsBoundedAndRespected(): void
    {
        $this->assertSame(3600, SlackResponse::fromTransport(new TransportResult(429, '', ['retry-after' => '999999']))->retryAfterSeconds);
        $this->assertNull(SlackResponse::fromTransport(new TransportResult(429, '', ['retry-after' => 'soon']))->retryAfterSeconds);

        $policy = new RetryPolicy(3, [60, 300]);
        $this->assertSame(60, $policy->delayAfter(1, null));
        $this->assertSame(300, $policy->delayAfter(2, null));
        $this->assertSame(300, $policy->delayAfter(9, null), 'last delay repeats');
        $this->assertSame(120, $policy->delayAfter(1, 120), 'Retry-After wins when longer');
        $this->assertSame(60, $policy->delayAfter(2, 5), 'short Retry-After still waits a minute');
        $this->assertFalse($policy->exhausted(2));
        $this->assertTrue($policy->exhausted(3));
    }
}
