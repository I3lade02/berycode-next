<?php

declare(strict_types=1);

namespace BeryCode\Support\Tests\Support;

use BeryCode\Support\Database\Migrator;
use PDO;

/**
 * Connection to the disposable test database (MariaDB/MySQL). Configure with
 * SUPPORT_TEST_DB_HOST/PORT/NAME/USER/PASSWORD; defaults match
 * `npm run support:test-db`. All support_* tables are dropped and migrated
 * once per run, then truncated before each test.
 */
final class TestDatabase
{
    public const SIGNING_SECRET = 'test-signing-secret-0123456789abcdef';
    public const TEAM_ID = 'T0TEAM0001';
    public const APP_ID = 'A0APP00001';
    public const STAFF = 'U0STAFF001';
    public const OTHER_STAFF = 'U0STAFF002';
    public const CRON_SECRET = 'test-cron-secret-0123456789abcdef0123';

    private static ?PDO $pdo = null;
    private static ?string $unavailable = null;

    /** @return array<string, string> */
    public static function connection(): array
    {
        $env = static fn (string $key, string $default): string => getenv($key) !== false && getenv($key) !== '' ? (string) getenv($key) : $default;

        return [
            'SUPPORT_DB_HOST' => $env('SUPPORT_TEST_DB_HOST', '127.0.0.1'),
            'SUPPORT_DB_PORT' => $env('SUPPORT_TEST_DB_PORT', '33306'),
            'SUPPORT_DB_NAME' => $env('SUPPORT_TEST_DB_NAME', 'support_test'),
            'SUPPORT_DB_USER' => $env('SUPPORT_TEST_DB_USER', 'root'),
            'SUPPORT_DB_PASSWORD' => $env('SUPPORT_TEST_DB_PASSWORD', 'support-test'),
        ];
    }

    /** @return array<string, string> */
    public static function baseConfig(): array
    {
        return self::connection() + [
            'SUPPORT_ENV' => 'test',
            'SUPPORT_HASH_SECRET' => 'test-hash-secret-0123456789abcdef0123456789',
            'SUPPORT_CRON_SECRET' => self::CRON_SECRET,
            'SLACK_BOT_TOKEN' => 'xoxb-test-token-not-real',
            'SLACK_SIGNING_SECRET' => self::SIGNING_SECRET,
            'SLACK_TEAM_ID' => self::TEAM_ID,
            'SLACK_APP_ID' => self::APP_ID,
            'SLACK_ALLOWED_STAFF_USER_IDS' => self::STAFF . ',' . self::OTHER_STAFF,
            'SUPPORT_SLACK_LOCALE' => 'en',
        ];
    }

    public static function available(): bool
    {
        try {
            self::pdo();

            return true;
        } catch (Skipped) {
            return false;
        }
    }

    public static function unavailableReason(): ?string
    {
        return self::$unavailable;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        if (self::$unavailable !== null) {
            throw new Skipped(self::$unavailable);
        }

        $c = self::connection();

        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $c['SUPPORT_DB_HOST'], $c['SUPPORT_DB_PORT'], $c['SUPPORT_DB_NAME']),
                $c['SUPPORT_DB_USER'],
                $c['SUPPORT_DB_PASSWORD'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 3,
                ],
            );
        } catch (\PDOException $exception) {
            self::$unavailable = sprintf(
                'test database %s:%s/%s unreachable (%s)',
                $c['SUPPORT_DB_HOST'],
                $c['SUPPORT_DB_PORT'],
                $c['SUPPORT_DB_NAME'],
                (string) ($exception->errorInfo[1] ?? $exception->getCode()),
            );

            throw new Skipped(self::$unavailable);
        }

        $pdo->exec("SET time_zone = '+00:00'");
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($pdo->query("SHOW TABLES LIKE 'support\\_%'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $pdo->exec('DROP TABLE `' . $table . '`');
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $migrator = new Migrator($pdo, dirname(__DIR__, 2) . '/migrations');
        $migrator->migrate();

        // Re-running must be a no-op.
        if ($migrator->migrate() !== []) {
            throw new \RuntimeException('Migrations are not idempotent.');
        }

        return self::$pdo = $pdo;
    }

    public static function truncate(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach (['support_ticket_events', 'support_ticket_issues', 'support_slack_interactions', 'support_rate_limits', 'support_tickets', 'support_project_keys', 'support_projects'] as $table) {
            $pdo->exec('TRUNCATE TABLE ' . $table);
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
