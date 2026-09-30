<?php

declare(strict_types=1);

namespace BeryCode\Support;

/**
 * Server-only configuration.
 *
 * Values come from environment variables (local development, CLI, tests) and/or
 * from a PHP file that returns an array with the same keys (Endora, where
 * environment variables cannot be set). Environment variables win.
 *
 * Nothing here is ever sent to the browser.
 */
final class Config
{
    public const ENV_PRODUCTION = 'production';
    public const ENV_DEVELOPMENT = 'development';
    public const ENV_TEST = 'test';

    public const DEFAULT_SLACK_API_BASE_URL = 'https://slack.com/api/';

    /** Every key the backend understands. Anything else in the config file is ignored. */
    public const KEYS = [
        'SUPPORT_ENV',
        'SUPPORT_DB_HOST',
        'SUPPORT_DB_PORT',
        'SUPPORT_DB_NAME',
        'SUPPORT_DB_USER',
        'SUPPORT_DB_PASSWORD',
        'SUPPORT_HASH_SECRET',
        'SUPPORT_CRON_SECRET',
        'SUPPORT_CRON_ALLOWED_IPS',
        'SUPPORT_CRON_TIME_BUDGET_SECONDS',
        'SUPPORT_ALLOWED_ORIGINS',
        'SUPPORT_CLIENT_IP_HEADER',
        'SUPPORT_TICKET_PREFIX',
        'SUPPORT_RATE_LIMIT_IP_PER_10_MIN',
        'SUPPORT_RATE_LIMIT_IP_PER_DAY',
        'SUPPORT_RATE_LIMIT_EMAIL_PER_HOUR',
        'SUPPORT_RATE_LIMIT_GLOBAL_PER_HOUR',
        'SLACK_BOT_TOKEN',
        'SLACK_SIGNING_SECRET',
        'SLACK_TEAM_ID',
        'SLACK_APP_ID',
        'SLACK_ALLOWED_STAFF_USER_IDS',
        'SUPPORT_SLACK_LOCALE',
        'SUPPORT_SLACK_MODE',
        'SUPPORT_SLACK_API_BASE_URL',
        'SUPPORT_SLACK_TIMEOUT_SECONDS',
        'SUPPORT_MOCK_SLACK_LOG',
    ];

    /** @var array<string, string> */
    private array $values;

    /** @param array<string, mixed> $values */
    private function __construct(array $values)
    {
        $clean = [];

        foreach (self::KEYS as $key) {
            if (!array_key_exists($key, $values) || $values[$key] === null) {
                continue;
            }

            $value = $values[$key];

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            if (is_array($value)) {
                $value = implode(',', array_map('strval', $value));
            }

            $value = trim((string) $value);

            if ($value !== '') {
                $clean[$key] = $value;
            }
        }

        $this->values = $clean;
    }

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): self
    {
        return new self($values);
    }

    /**
     * Loads the first config file found (see candidateFiles()) and overlays
     * environment variables.
     */
    public static function load(): self
    {
        $values = [];

        foreach (self::candidateFiles() as $file) {
            // open_basedir may forbid looking outside the web root; that is fine.
            if (!@is_file($file)) {
                continue;
            }

            $loaded = require $file;

            if (!is_array($loaded)) {
                throw new ConfigException('Support config file must return an array.');
            }

            $values = $loaded;
            break;
        }

        foreach (self::KEYS as $key) {
            $env = getenv($key);

            if ($env !== false && trim($env) !== '') {
                $values[$key] = $env;
            }
        }

        return new self($values);
    }

    /** @return list<string> */
    public static function candidateFiles(): array
    {
        $files = [];
        $explicit = getenv('SUPPORT_CONFIG_FILE');

        if ($explicit !== false && $explicit !== '') {
            $files[] = $explicit;
        }

        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';

        if (is_string($documentRoot) && $documentRoot !== '') {
            // Preferred on shared hosting: one level above the public web root.
            $files[] = dirname(rtrim($documentRoot, '/')) . '/berycode-support-config.php';
        }

        $files[] = dirname(__DIR__) . '/config.php';

        return $files;
    }

    public function env(): string
    {
        $env = $this->values['SUPPORT_ENV'] ?? self::ENV_PRODUCTION;

        if (!in_array($env, [self::ENV_PRODUCTION, self::ENV_DEVELOPMENT, self::ENV_TEST], true)) {
            throw new ConfigException('SUPPORT_ENV must be production, development or test.');
        }

        return $env;
    }

    public function isProduction(): bool
    {
        return $this->env() === self::ENV_PRODUCTION;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->values[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->values[$key]);
    }

    public function int(string $key, int $default, int $min, int $max): int
    {
        $raw = $this->values[$key] ?? null;

        if ($raw === null) {
            return $default;
        }

        if (!preg_match('/^\d+$/', $raw)) {
            throw new ConfigException($key . ' must be a whole number.');
        }

        return max($min, min($max, (int) $raw));
    }

    /**
     * @param list<string> $keys
     */
    public function requireKeys(array $keys): void
    {
        $missing = array_values(array_filter($keys, fn (string $key): bool => !$this->has($key)));

        if ($missing !== []) {
            throw ConfigException::missing($missing);
        }
    }

    /**
     * @return array{host: string, port: int, name: string, user: string, password: string}
     */
    public function database(): array
    {
        $this->requireKeys(['SUPPORT_DB_HOST', 'SUPPORT_DB_NAME', 'SUPPORT_DB_USER']);

        return [
            'host' => (string) $this->get('SUPPORT_DB_HOST'),
            'port' => $this->int('SUPPORT_DB_PORT', 3306, 1, 65535),
            'name' => (string) $this->get('SUPPORT_DB_NAME'),
            'user' => (string) $this->get('SUPPORT_DB_USER'),
            'password' => (string) $this->get('SUPPORT_DB_PASSWORD', ''),
        ];
    }

    public function hashSecret(): string
    {
        $this->requireKeys(['SUPPORT_HASH_SECRET']);
        $secret = (string) $this->get('SUPPORT_HASH_SECRET');

        if ($this->isProduction() && strlen($secret) < 32) {
            throw new ConfigException('SUPPORT_HASH_SECRET must be at least 32 characters.');
        }

        return $secret;
    }

    public function ticketPrefix(): string
    {
        $prefix = strtoupper((string) $this->get('SUPPORT_TICKET_PREFIX', 'BC'));

        if (!preg_match('/^[A-Z]{1,6}$/', $prefix)) {
            throw new ConfigException('SUPPORT_TICKET_PREFIX must be 1-6 letters.');
        }

        return $prefix;
    }

    /** "live" posts to Slack; "mock" (development/test only) logs payloads locally. */
    public function slackMode(): string
    {
        $mode = strtolower((string) $this->get('SUPPORT_SLACK_MODE', 'live'));

        if (!in_array($mode, ['live', 'mock'], true)) {
            throw new ConfigException('SUPPORT_SLACK_MODE must be live or mock.');
        }

        if ($mode === 'mock' && $this->isProduction()) {
            throw new ConfigException('SUPPORT_SLACK_MODE=mock is not allowed in production.');
        }

        return $mode;
    }

    public function slackApiBaseUrl(): string
    {
        $override = $this->get('SUPPORT_SLACK_API_BASE_URL');

        if ($override === null) {
            return self::DEFAULT_SLACK_API_BASE_URL;
        }

        if ($this->isProduction()) {
            throw new ConfigException('SUPPORT_SLACK_API_BASE_URL can only be overridden outside production.');
        }

        return rtrim($override, '/') . '/';
    }

    public function slackBotToken(): ?string
    {
        return $this->get('SLACK_BOT_TOKEN');
    }

    public function slackSigningSecret(): ?string
    {
        return $this->get('SLACK_SIGNING_SECRET');
    }

    public function slackTeamId(): ?string
    {
        return $this->get('SLACK_TEAM_ID');
    }

    public function slackAppId(): ?string
    {
        return $this->get('SLACK_APP_ID');
    }

    /** @return list<string> */
    public function staffUserIds(): array
    {
        $raw = (string) $this->get('SLACK_ALLOWED_STAFF_USER_IDS', '');
        $ids = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($ids as $id) {
            if (!preg_match('/^[UW][A-Z0-9]{2,31}$/', $id)) {
                throw new ConfigException('SLACK_ALLOWED_STAFF_USER_IDS must contain Slack user IDs such as U0123ABCD.');
            }
        }

        return array_values(array_unique($ids));
    }

    public function slackLocale(): string
    {
        $locale = strtolower((string) $this->get('SUPPORT_SLACK_LOCALE', 'cs'));

        return $locale === 'en' ? 'en' : 'cs';
    }

    public function slackTimeoutSeconds(): int
    {
        return $this->int('SUPPORT_SLACK_TIMEOUT_SECONDS', 8, 2, 30);
    }

    /** @return list<string> */
    public function allowedOrigins(): array
    {
        $raw = (string) $this->get('SUPPORT_ALLOWED_ORIGINS', '');

        return array_values(array_map(
            static fn (string $origin): string => rtrim(strtolower($origin), '/'),
            preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [],
        ));
    }

    /** @return list<string> */
    public function cronAllowedIps(): array
    {
        $raw = (string) $this->get('SUPPORT_CRON_ALLOWED_IPS', '');

        return preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Problems that would prevent a correct production setup. Only key names are
     * reported.
     *
     * @return list<string>
     */
    public function productionProblems(): array
    {
        $problems = [];

        foreach ([
            'SUPPORT_DB_HOST',
            'SUPPORT_DB_NAME',
            'SUPPORT_DB_USER',
            'SUPPORT_HASH_SECRET',
            'SUPPORT_CRON_SECRET',
            'SLACK_BOT_TOKEN',
            'SLACK_SIGNING_SECRET',
            'SLACK_TEAM_ID',
            'SLACK_ALLOWED_STAFF_USER_IDS',
        ] as $key) {
            if (!$this->has($key)) {
                $problems[] = $key . ' is not set';
            }
        }

        $checks = [
            fn () => $this->env(),
            fn () => $this->slackMode(),
            fn () => $this->slackApiBaseUrl(),
            fn () => $this->staffUserIds(),
            fn () => $this->ticketPrefix(),
            fn () => $this->has('SUPPORT_HASH_SECRET') ? $this->hashSecret() : null,
        ];

        foreach ($checks as $check) {
            try {
                $check();
            } catch (ConfigException $exception) {
                $problems[] = $exception->getMessage();
            }
        }

        $token = $this->slackBotToken();

        if ($token !== null && !str_starts_with($token, 'xoxb-')) {
            $problems[] = 'SLACK_BOT_TOKEN must be a bot token (xoxb-...)';
        }

        $cronSecret = $this->get('SUPPORT_CRON_SECRET');

        if ($cronSecret !== null && strlen($cronSecret) < 32) {
            $problems[] = 'SUPPORT_CRON_SECRET must be at least 32 characters';
        }

        if ($this->isProduction() && $this->allowedOrigins() === []) {
            $problems[] = 'SUPPORT_ALLOWED_ORIGINS is not set (recommended: https://berycode.cz,https://www.berycode.cz)';
        }

        return array_values(array_unique($problems));
    }
}
