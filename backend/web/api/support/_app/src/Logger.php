<?php

declare(strict_types=1);

namespace BeryCode\Support;

/**
 * Structured JSON-line logger. Callers pass identifiers and sanitized codes only:
 * never customer descriptions, emails, tokens or raw exception messages (PDO
 * messages can contain row data).
 */
final class Logger
{
    /** @var callable(string): void */
    private $sink;

    /** @param (callable(string): void)|null $sink */
    public function __construct(?callable $sink = null)
    {
        $this->sink = $sink ?? static function (string $line): void {
            error_log($line);
        };
    }

    /** @param array<string, scalar|null> $context */
    public function info(string $event, array $context = []): void
    {
        $this->write('info', $event, $context);
    }

    /** @param array<string, scalar|null> $context */
    public function warning(string $event, array $context = []): void
    {
        $this->write('warning', $event, $context);
    }

    /** @param array<string, scalar|null> $context */
    public function error(string $event, array $context = []): void
    {
        $this->write('error', $event, $context);
    }

    /**
     * Safe description of an exception: class, code and location. The message is
     * deliberately omitted.
     *
     * @return array<string, scalar|null>
     */
    public static function exceptionContext(\Throwable $exception): array
    {
        $code = $exception->getCode();

        if ($exception instanceof \PDOException && is_array($exception->errorInfo)) {
            $code = (string) ($exception->errorInfo[0] ?? '') . '/' . (string) ($exception->errorInfo[1] ?? '');
        }

        $context = [
            'exception' => get_class($exception),
            'code' => is_scalar($code) ? (string) $code : null,
            'at' => basename($exception->getFile()) . ':' . $exception->getLine(),
        ];

        if ($exception instanceof ConfigException) {
            // Config messages only ever contain key names.
            $context['detail'] = $exception->getMessage();
        }

        return $context;
    }

    /** @param array<string, scalar|null> $context */
    private function write(string $level, string $event, array $context): void
    {
        $record = ['level' => $level, 'event' => $event];

        foreach ($context as $key => $value) {
            if (is_string($value)) {
                $value = preg_replace('/[\x00-\x1F\x7F]/', ' ', $value) ?? '';
                $value = mb_substr($value, 0, 200, 'UTF-8');
            } elseif (!is_scalar($value) && $value !== null) {
                continue;
            }

            $record[(string) $key] = $value;
        }

        $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        ($this->sink)('[berycode-support] ' . ($line === false ? '{}' : $line));
    }
}
