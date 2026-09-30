<?php

declare(strict_types=1);

namespace BeryCode\Support\Http;

final class HttpRequest
{
    /**
     * @param array<string, string> $headers lower-case name => value
     * @param array<string, mixed> $query
     * @param array<string, mixed> $server
     */
    public function __construct(
        public readonly string $method,
        public readonly array $headers,
        public readonly string $body,
        public readonly array $query = [],
        public readonly array $server = [],
        public readonly bool $bodyTooLarge = false,
        public readonly bool $cli = false,
    ) {
    }

    /**
     * Reads at most $maxBodyBytes of the raw body. Larger bodies are not read at
     * all and flagged, so the endpoint can answer 413 cheaply.
     */
    public static function fromGlobals(int $maxBodyBytes): self
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            } elseif ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
                $headers[strtolower(str_replace('_', '-', $key))] = $value;
            }
        }

        $declaredLength = (int) ($headers['content-length'] ?? 0);
        $tooLarge = $declaredLength > $maxBodyBytes;
        $body = '';

        if (!$tooLarge) {
            $body = (string) file_get_contents('php://input', false, null, 0, $maxBodyBytes + 1);
            $tooLarge = strlen($body) > $maxBodyBytes;
        }

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $headers,
            $tooLarge ? '' : $body,
            $_GET,
            $_SERVER,
            $tooLarge,
            PHP_SAPI === 'cli',
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** Media type without parameters, e.g. "application/json". */
    public function contentType(): string
    {
        return strtolower(trim(explode(';', $this->header('content-type') ?? '')[0]));
    }
}
