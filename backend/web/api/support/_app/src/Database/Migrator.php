<?php

declare(strict_types=1);

namespace BeryCode\Support\Database;

use PDO;

/**
 * Applies backend/migrations/*.sql in filename order and records each version.
 * MySQL DDL auto-commits, so each file should be safe to re-run (IF NOT EXISTS).
 */
final class Migrator
{
    public function __construct(private PDO $pdo, private string $directory)
    {
    }

    /**
     * @param (callable(string): void)|null $output
     * @return list<string> versions applied in this run
     */
    public function migrate(?callable $output = null): array
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS support_schema_migrations (
                version VARCHAR(100) NOT NULL,
                applied_at DATETIME(3) NOT NULL,
                PRIMARY KEY (version)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );

        $applied = [];

        foreach ($this->pending() as $version => $file) {
            $sql = file_get_contents($file);

            if ($sql === false) {
                throw new \RuntimeException('Cannot read migration ' . $version);
            }

            foreach (self::splitStatements($sql) as $statement) {
                $this->pdo->exec($statement);
            }

            // Files record their own version too (for phpMyAdmin imports), hence IGNORE.
            $insert = $this->pdo->prepare('INSERT IGNORE INTO support_schema_migrations (version, applied_at) VALUES (?, UTC_TIMESTAMP(3))');
            $insert->execute([$version]);
            $applied[] = $version;

            if ($output !== null) {
                $output('Applied ' . $version);
            }
        }

        return $applied;
    }

    /** @return array<string, string> version => file path */
    public function pending(): array
    {
        $done = [];

        try {
            $rows = $this->pdo->query('SELECT version FROM support_schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
            $done = array_flip(array_map('strval', $rows));
        } catch (\PDOException) {
            // Table does not exist yet: everything is pending.
        }

        $files = glob(rtrim($this->directory, '/') . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        $pending = [];

        foreach ($files as $file) {
            $version = basename($file, '.sql');

            if (!isset($done[$version])) {
                $pending[$version] = $file;
            }
        }

        return $pending;
    }

    /** @return list<string> */
    public static function splitStatements(string $sql): array
    {
        $withoutComments = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $parts = preg_split('/;\s*(?:\r?\n|$)/', $withoutComments) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));
    }
}
