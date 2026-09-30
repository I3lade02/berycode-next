<?php

declare(strict_types=1);

namespace BeryCode\Support\Tests\Integration;

use BeryCode\Support\Database\Migrator;
use BeryCode\Support\Tests\Support\TestCase;
use BeryCode\Support\Tests\Support\TestDatabase;
use PDO;

/**
 * A database created with only 001 (single-issue tickets) must upgrade to 002
 * without losing ticket content. Uses a throwaway database.
 */
final class MigrationUpgradeTest extends TestCase
{
    public const NEEDS_DATABASE = true;
    private const DATABASE = 'support_test_upgrade';

    private ?PDO $pdo = null;
    private string $tempDir = '';

    public function setUp(): void
    {
        $root = TestDatabase::pdo();

        try {
            $root->exec('DROP DATABASE IF EXISTS ' . self::DATABASE);
            $root->exec('CREATE DATABASE ' . self::DATABASE . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (\PDOException) {
            $this->skip('test user cannot create databases');
        }

        $c = TestDatabase::connection();
        $this->pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $c['SUPPORT_DB_HOST'], $c['SUPPORT_DB_PORT'], self::DATABASE),
            $c['SUPPORT_DB_USER'],
            $c['SUPPORT_DB_PASSWORD'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
        );

        $this->tempDir = sys_get_temp_dir() . '/support-migrations-' . bin2hex(random_bytes(4));
        mkdir($this->tempDir);
        copy(dirname(__DIR__, 2) . '/migrations/001_initial_schema.sql', $this->tempDir . '/001_initial_schema.sql');
    }

    public function tearDown(): void
    {
        TestDatabase::pdo()->exec('DROP DATABASE IF EXISTS ' . self::DATABASE);

        if ($this->tempDir !== '') {
            array_map('unlink', glob($this->tempDir . '/*') ?: []);
            rmdir($this->tempDir);
        }
    }

    public function testPhpMyAdminImportIsRecognizedByTheMigrator(): void
    {
        // phpMyAdmin runs the files as plain SQL, in order.
        foreach (glob(dirname(__DIR__, 2) . '/migrations/*.sql') ?: [] as $file) {
            foreach (Migrator::splitStatements((string) file_get_contents($file)) as $statement) {
                $this->pdo->exec($statement);
            }
        }

        $this->assertSame([], (new Migrator($this->pdo, dirname(__DIR__, 2) . '/migrations'))->migrate(), 'nothing re-applied');
    }

    public function testExistingTicketsBecomeSingleIssueTickets(): void
    {
        (new Migrator($this->pdo, $this->tempDir))->migrate();

        $this->pdo->exec("INSERT INTO support_projects (code, display_name, slack_channel_id, created_at, updated_at)
            VALUES ('acme-web', 'Acme', 'C0ACME0001', NOW(3), NOW(3))");
        $this->pdo->exec("INSERT INTO support_tickets (reference, project_id, customer_name, customer_email, subject, description,
                request_type, priority, locale, idempotency_key, submission_fingerprint, created_at, updated_at, slack_channel_id)
            VALUES ('BC-000001', 1, 'Jana', 'jana@example.com', 'Old subject', 'Old description kept after upgrade.',
                'CHANGE_REQUEST', 'HIGH', 'cs', 'key-0000000000000001', REPEAT('a', 64), '2026-01-01 10:00:00', NOW(3), 'C0ACME0001')");

        $applied = (new Migrator($this->pdo, dirname(__DIR__, 2) . '/migrations'))->migrate();

        $this->assertSame(['002_ticket_issues'], $applied);
        $issue = $this->pdo->query('SELECT * FROM support_ticket_issues')->fetchAll();
        $this->assertCount(1, $issue);
        $this->assertSame(
            ['1', 'CHANGE_REQUEST', 'HIGH', 'Old subject', 'Old description kept after upgrade.', '2026-01-01 10:00:00.000'],
            [(string) $issue[0]['position'], $issue[0]['request_type'], $issue[0]['priority'], $issue[0]['subject'], $issue[0]['description'], $issue[0]['created_at']],
        );

        $columns = $this->pdo->query('SHOW COLUMNS FROM support_tickets')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertFalse(in_array('description', $columns, true));
        $this->assertFalse(in_array('request_type', $columns, true));
        $this->assertTrue(in_array('subject', $columns, true) && in_array('priority', $columns, true));
    }
}
