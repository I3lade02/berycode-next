<?php

declare(strict_types=1);

namespace BeryCode\Support\Cli;

/**
 * Minimal KEY=VALUE loader for local development and the CLI. Never used by
 * production web requests (Endora reads the PHP config file). Existing
 * environment variables are never overridden.
 */
final class DotEnv
{
    public static function load(string $file): bool
    {
        if (!is_file($file) || !is_readable($file)) {
            return false;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            $parts = explode('=', $line, 2);

            if (count($parts) !== 2 || !preg_match('/^[A-Z][A-Z0-9_]*$/', trim($parts[0]))) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            } else {
                $value = trim((string) preg_replace('/\s+#.*$/', '', $value));
            }

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }

        return true;
    }
}
