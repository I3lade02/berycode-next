<?php

declare(strict_types=1);

namespace BeryCode\Support\Http;

final class HttpResponse
{
    /** @var list<callable(): void> work to run after the response has been sent */
    public array $deferred = [];

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly ?array $json = null,
        public readonly array $headers = [],
    ) {
    }

    /** @param array<string, mixed> $json */
    public static function json(int $status, array $json, array $headers = []): self
    {
        return new self($status, $json, $headers);
    }

    public static function empty(int $status = 200): self
    {
        return new self($status);
    }

    /** @param callable(): void $task */
    public function defer(callable $task): self
    {
        $this->deferred[] = $task;

        return $this;
    }
}
