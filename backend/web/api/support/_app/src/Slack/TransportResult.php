<?php

declare(strict_types=1);

namespace BeryCode\Support\Slack;

final class TransportResult
{
    /**
     * @param array<string, string> $headers lower-case header name => value
     * @param bool $requestSent whether the request may have reached Slack (an
     *                          error after this point is ambiguous)
     */
    public function __construct(
        public readonly ?int $status,
        public readonly string $body,
        public readonly array $headers = [],
        public readonly ?string $networkError = null,
        public readonly bool $requestSent = true,
    ) {
    }

    /** @param array<string, mixed> $json */
    public static function json(array $json, int $status = 200, array $headers = []): self
    {
        return new self($status, (string) json_encode($json), $headers);
    }

    public static function networkError(string $code, bool $requestSent): self
    {
        return new self(null, '', [], $code, $requestSent);
    }
}
