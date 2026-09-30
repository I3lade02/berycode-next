<?php

declare(strict_types=1);

namespace BeryCode\Support\Tests\Unit;

use BeryCode\Support\Slack\SlackSignatureVerifier;
use BeryCode\Support\Tests\Support\FixedClock;
use BeryCode\Support\Tests\Support\TestCase;

final class SlackSignatureVerifierTest extends TestCase
{
    private const SECRET = '8f742231b10e8888abcd99yyyzzz85a5';

    public function testMatchesSlackDocumentationExample(): void
    {
        // Example from https://docs.slack.dev/authentication/verifying-requests-from-slack/
        $body = 'token=xyzz0WbapA4vBCDEFasx0q6G&team_id=T1DC2JH3J&team_domain=testteamnow&channel_id=G8PSS9T3V&channel_name=foobar&user_id=U2CERLKJA&user_name=roadrunner&command=%2Fwebhook-collect&text=&response_url=https%3A%2F%2Fhooks.slack.com%2Fcommands%2FT1DC2JH3J%2F397700885554%2F96rGlfmibIGlgcZRskXaIFfN&trigger_id=398738663015.47445629121.803a0bc887a14d10d2c447fce8b6703c';
        $clock = new FixedClock('@1531420618');
        $verifier = new SlackSignatureVerifier(self::SECRET, $clock);

        $this->assertSame(
            SlackSignatureVerifier::OK,
            $verifier->verify('1531420618', 'v0=a2114d57b48eac39b9ad189dd8316235a7b4a8d21a10bd27519666489c69b503', $body),
        );
    }

    public function testRejectsTamperingWrongSecretAndMalformedHeaders(): void
    {
        $clock = new FixedClock('@1700000000');
        $verifier = new SlackSignatureVerifier(self::SECRET, $clock);
        $body = 'payload=%7B%22type%22%3A%22block_actions%22%7D';
        $signature = SlackSignatureVerifier::sign(self::SECRET, 1700000000, $body);

        $this->assertSame('ok', $verifier->verify('1700000000', $signature, $body));
        $this->assertSame('bad_signature', $verifier->verify('1700000000', $signature, $body . 'x'));
        $this->assertSame('bad_signature', $verifier->verify('1700000000', SlackSignatureVerifier::sign('other-secret', 1700000000, $body), $body));
        $this->assertSame('bad_signature', $verifier->verify('1700000001', $signature, $body), 'timestamp is part of the signature');
        $this->assertSame('missing_signature', $verifier->verify('1700000000', null, $body));
        $this->assertSame('missing_signature', $verifier->verify('1700000000', 'v1=' . substr($signature, 3), $body));
        $this->assertSame('missing_timestamp', $verifier->verify(null, $signature, $body));
        $this->assertSame('missing_timestamp', $verifier->verify('17e8', $signature, $body));
        $this->assertSame('not_configured', (new SlackSignatureVerifier('', $clock))->verify('1700000000', $signature, $body));
    }

    public function testRejectsStaleAndFutureTimestamps(): void
    {
        $body = 'payload=%7B%7D';
        $clock = new FixedClock('@1700000000');
        $verifier = new SlackSignatureVerifier(self::SECRET, $clock);

        foreach ([1700000000 - 301, 1700000000 + 301] as $timestamp) {
            $this->assertSame('stale_timestamp', $verifier->verify((string) $timestamp, SlackSignatureVerifier::sign(self::SECRET, $timestamp, $body), $body));
        }

        $timestamp = 1700000000 - 299;
        $this->assertSame('ok', $verifier->verify((string) $timestamp, SlackSignatureVerifier::sign(self::SECRET, $timestamp, $body), $body));
    }
}
