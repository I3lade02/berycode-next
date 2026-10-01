<?php

declare(strict_types=1);

namespace BeryCode\Support\Projects;

use PDO;

final class ProjectRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Active projects owning the normalized key. The primary key on lookup_key
     * means at most one row in a healthy database; callers still treat more than
     * one as ambiguous.
     *
     * @return list<Project>
     */
    public function findActiveByKey(string $normalizedKey): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DISTINCT p.id, p.code, p.display_name, p.slack_channel_id, p.is_active, p.is_public
             FROM support_project_keys k
             JOIN support_projects p ON p.id = k.project_id
             WHERE k.lookup_key = ? AND p.is_active = 1',
        );
        $statement->execute([$normalizedKey]);

        return array_map([Project::class, 'fromRow'], $statement->fetchAll());
    }

    /**
     * Keys of active projects explicitly marked safe for public display.
     *
     * @return list<array{display_name: string, lookup_key: string}>
     */
    public function publicKeys(): array
    {
        $statement = $this->pdo->query(
            'SELECT p.display_name, k.lookup_key
             FROM support_projects p
             JOIN support_project_keys k ON k.project_id = p.id
             WHERE p.is_active = 1 AND p.is_public = 1
             ORDER BY p.display_name',
        );

        return $statement->fetchAll();
    }

    /**
     * Active projects offered in the support form's project list.
     *
     * @return list<array{code: string, name: string}>
     */
    public function listActive(): array
    {
        $rows = $this->pdo->query(
            'SELECT code, display_name FROM support_projects WHERE is_active = 1 ORDER BY display_name, code',
        )->fetchAll();

        return array_map(
            static fn (array $row): array => ['code' => (string) $row['code'], 'name' => (string) $row['display_name']],
            $rows,
        );
    }

    /** @return list<array<string, mixed>> projects with a comma-separated key list */
    public function listAll(): array
    {
        return $this->pdo->query(
            "SELECT p.id, p.code, p.display_name, p.slack_channel_id, p.is_active, p.is_public,
                    GROUP_CONCAT(CASE WHEN k.key_type = 'alias' THEN k.lookup_key END ORDER BY k.lookup_key SEPARATOR ', ') AS aliases
             FROM support_projects p
             LEFT JOIN support_project_keys k ON k.project_id = p.id
             GROUP BY p.id, p.code, p.display_name, p.slack_channel_id, p.is_active, p.is_public
             ORDER BY p.code",
        )->fetchAll();
    }
}
