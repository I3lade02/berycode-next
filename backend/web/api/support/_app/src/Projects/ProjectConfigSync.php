<?php

declare(strict_types=1);

namespace BeryCode\Support\Projects;

use BeryCode\Support\Clock;
use BeryCode\Support\Database\Database;
use BeryCode\Support\Text;
use BeryCode\Support\Time;
use PDO;

/**
 * Applies a projects JSON file (see backend/config/projects.example.json) to the
 * database. Upserts by code, replaces each listed project's lookup keys and
 * refuses any configuration where one normalized name/code/alias would point to
 * two projects.
 */
final class ProjectConfigSync
{
    public function __construct(private PDO $pdo, private Clock $clock)
    {
    }

    /**
     * @return list<ProjectDefinition>
     * @throws ProjectConfigException
     */
    public static function parse(mixed $json): array
    {
        $errors = [];
        $definitions = [];
        $owners = [];

        $projects = is_array($json) && array_key_exists('projects', $json) ? $json['projects'] : null;

        if (!is_array($projects) || !array_is_list($projects) || $projects === []) {
            throw new ProjectConfigException(['The file must contain a non-empty "projects" array.']);
        }

        foreach ($projects as $index => $item) {
            $label = 'projects[' . $index . ']';

            if (!is_array($item)) {
                $errors[] = $label . ' must be an object.';
                continue;
            }

            $code = is_string($item['code'] ?? null) ? ProjectKey::normalize($item['code']) : '';

            if (!ProjectKey::isValidCode($code)) {
                $errors[] = $label . '.code must be 2-64 characters: lower-case letters, digits, ".", "_" or "-".';
                continue;
            }

            $label = 'Project "' . $code . '"';
            $name = is_string($item['name'] ?? null) ? Text::singleLine($item['name']) : '';

            if ($name === '' || Text::length($name) > ProjectKey::MAX_LENGTH) {
                $errors[] = $label . ': name is required (max ' . ProjectKey::MAX_LENGTH . ' characters).';
            }

            $channel = is_string($item['slackChannelId'] ?? null) ? trim($item['slackChannelId']) : '';

            if (!ProjectKey::isValidChannelId($channel)) {
                $errors[] = $label . ': slackChannelId must be a Slack channel ID such as C0123ABCD (not a channel name).';
            }

            $aliases = [];
            $rawAliases = $item['aliases'] ?? [];

            if (!is_array($rawAliases) || !array_is_list($rawAliases)) {
                $errors[] = $label . ': aliases must be an array of strings.';
                $rawAliases = [];
            }

            foreach ($rawAliases as $alias) {
                $clean = is_string($alias) ? Text::singleLine($alias) : '';

                if ($clean === '' || Text::length($clean) > ProjectKey::MAX_LENGTH) {
                    $errors[] = $label . ': every alias must be a non-empty string (max ' . ProjectKey::MAX_LENGTH . ' characters).';
                    continue;
                }

                $aliases[] = $clean;
            }

            foreach (['active', 'public'] as $flag) {
                if (array_key_exists($flag, $item) && !is_bool($item[$flag])) {
                    $errors[] = $label . ': ' . $flag . ' must be true or false.';
                }
            }

            if (isset($definitions[$code])) {
                $errors[] = $label . ' is listed more than once.';
                continue;
            }

            // Code wins over name, name over alias when they normalize identically.
            $keys = [$code => 'code'];
            $keys += [ProjectKey::normalize($name) => 'name'];

            foreach ($aliases as $alias) {
                $keys += [ProjectKey::normalize($alias) => 'alias'];
            }

            unset($keys['']);

            foreach (array_keys($keys) as $key) {
                $key = (string) $key;

                if (isset($owners[$key]) && $owners[$key] !== $code) {
                    $errors[] = 'Ambiguous: "' . $key . '" is used by both "' . $owners[$key] . '" and "' . $code . '".';
                }

                $owners[$key] ??= $code;
            }

            $definitions[$code] = new ProjectDefinition(
                $code,
                $name,
                $aliases,
                $keys,
                $channel,
                ($item['active'] ?? true) === true,
                ($item['public'] ?? false) === true,
            );
        }

        if ($errors !== []) {
            throw new ProjectConfigException(array_values(array_unique($errors)));
        }

        return array_values($definitions);
    }

    /**
     * The same change as apply(), as SQL to paste into phpMyAdmin when the
     * database is not reachable from the operator's machine. Collisions with
     * projects missing from the file surface as a duplicate-key error, and the
     * transaction is then not committed.
     *
     * @param list<ProjectDefinition> $definitions
     */
    public static function toSql(array $definitions, bool $deactivateMissing = false): string
    {
        $codes = implode(', ', array_map(static fn (ProjectDefinition $d): string => self::literal($d->code), $definitions));
        $sql = [
            '-- Generated by `npm run support -- projects:sync <file> --print-sql`.',
            '-- Paste into phpMyAdmin (SQL tab) for the support database. Safe to run again.',
            '-- A "Duplicate entry" error means a code/name/alias is already used by another project.',
            'START TRANSACTION;',
            '',
        ];

        foreach ($definitions as $definition) {
            $sql[] = sprintf(
                "INSERT INTO support_projects (code, display_name, slack_channel_id, is_active, is_public, created_at, updated_at)\n"
                . "VALUES (%s, %s, %s, %d, %d, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3))\n"
                . "ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), slack_channel_id = VALUES(slack_channel_id),\n"
                . "    is_active = VALUES(is_active), is_public = VALUES(is_public), updated_at = VALUES(updated_at);",
                self::literal($definition->code),
                self::literal($definition->displayName),
                self::literal($definition->slackChannelId),
                (int) $definition->active,
                (int) $definition->public,
            );
        }

        $sql[] = '';
        $sql[] = "DELETE k FROM support_project_keys k JOIN support_projects p ON p.id = k.project_id WHERE p.code IN ({$codes});";
        $sql[] = '';

        foreach ($definitions as $definition) {
            foreach ($definition->keys as $key => $type) {
                $sql[] = sprintf(
                    'INSERT INTO support_project_keys (lookup_key, project_id, key_type) SELECT %s, id, %s FROM support_projects WHERE code = %s;',
                    self::literal((string) $key),
                    self::literal($type),
                    self::literal($definition->code),
                );
            }
        }

        if ($deactivateMissing) {
            $sql[] = '';
            $sql[] = "UPDATE support_projects SET is_active = 0, updated_at = UTC_TIMESTAMP(3) WHERE is_active = 1 AND code NOT IN ({$codes});";
        }

        $sql[] = '';
        $sql[] = 'COMMIT;';

        return implode("\n", $sql) . "\n";
    }

    /**
     * SQL string literal that is safe regardless of sql_mode: plain quotes with
     * doubled single quotes, or a hex literal when a backslash is present.
     */
    private static function literal(string $value): string
    {
        if (str_contains($value, '\\') || preg_match('/[\x00-\x1F]/', $value)) {
            return "CONVERT(X'" . bin2hex($value) . "' USING utf8mb4)";
        }

        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * @param list<ProjectDefinition> $definitions
     * @return list<string> human-readable report
     * @throws ProjectConfigException
     */
    public function apply(array $definitions, bool $deactivateMissing = false, bool $dryRun = false): array
    {
        $now = Time::db($this->clock->now());
        $report = [];
        $codes = array_map(static fn (ProjectDefinition $definition): string => $definition->code, $definitions);

        $this->pdo->beginTransaction();

        try {
            $this->assertNoCollisionsWithOtherProjects($definitions, $codes);

            $ids = [];

            foreach ($definitions as $definition) {
                $select = $this->pdo->prepare('SELECT id, slack_channel_id FROM support_projects WHERE code = ? FOR UPDATE');
                $select->execute([$definition->code]);
                $existing = $select->fetch();

                if ($existing === false) {
                    $insert = $this->pdo->prepare(
                        'INSERT INTO support_projects (code, display_name, slack_channel_id, is_active, is_public, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?)',
                    );
                    $insert->execute([
                        $definition->code,
                        $definition->displayName,
                        $definition->slackChannelId,
                        (int) $definition->active,
                        (int) $definition->public,
                        $now,
                        $now,
                    ]);
                    $ids[$definition->code] = (int) $this->pdo->lastInsertId();
                    $report[] = 'Created ' . $definition->code;
                    continue;
                }

                $update = $this->pdo->prepare(
                    'UPDATE support_projects
                     SET display_name = ?, slack_channel_id = ?, is_active = ?, is_public = ?, updated_at = ?
                     WHERE id = ?',
                );
                $update->execute([
                    $definition->displayName,
                    $definition->slackChannelId,
                    (int) $definition->active,
                    (int) $definition->public,
                    $now,
                    $existing['id'],
                ]);
                $ids[$definition->code] = (int) $existing['id'];
                $report[] = 'Updated ' . $definition->code;

                if ($existing['slack_channel_id'] !== $definition->slackChannelId) {
                    $report[] = '  channel changed for ' . $definition->code . ': new tickets only; existing tickets keep their original channel';
                }
            }

            // Delete every listed project's keys first so keys can move between
            // projects within one file.
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $this->pdo->prepare('DELETE FROM support_project_keys WHERE project_id IN (' . $placeholders . ')')
                ->execute(array_values($ids));

            $insertKey = $this->pdo->prepare('INSERT INTO support_project_keys (lookup_key, project_id, key_type) VALUES (?, ?, ?)');

            foreach ($definitions as $definition) {
                foreach ($definition->keys as $key => $type) {
                    $insertKey->execute([(string) $key, $ids[$definition->code], $type]);
                }
            }

            if ($deactivateMissing) {
                $codePlaceholders = implode(',', array_fill(0, count($codes), '?'));
                $deactivate = $this->pdo->prepare(
                    'UPDATE support_projects SET is_active = 0, updated_at = ?
                     WHERE is_active = 1 AND code NOT IN (' . $codePlaceholders . ')',
                );
                $deactivate->execute([$now, ...$codes]);
                $report[] = 'Deactivated ' . $deactivate->rowCount() . ' project(s) missing from the file';
            }

            if ($dryRun) {
                $this->pdo->rollBack();
                $report[] = 'Dry run: nothing was written.';

                return $report;
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            if ($exception instanceof \PDOException && Database::isDuplicateKey($exception)) {
                throw new ProjectConfigException(['A code, name or alias is already used by another project.']);
            }

            throw $exception;
        }

        return $report;
    }

    /**
     * @param list<ProjectDefinition> $definitions
     * @param list<string> $codes
     */
    private function assertNoCollisionsWithOtherProjects(array $definitions, array $codes): void
    {
        $keys = [];

        foreach ($definitions as $definition) {
            foreach (array_keys($definition->keys) as $key) {
                $keys[] = (string) $key;
            }
        }

        $keyPlaceholders = implode(',', array_fill(0, count($keys), '?'));
        $codePlaceholders = implode(',', array_fill(0, count($codes), '?'));
        $statement = $this->pdo->prepare(
            'SELECT k.lookup_key, p.code, p.is_active
             FROM support_project_keys k
             JOIN support_projects p ON p.id = k.project_id
             WHERE k.lookup_key IN (' . $keyPlaceholders . ') AND p.code NOT IN (' . $codePlaceholders . ')',
        );
        $statement->execute([...$keys, ...$codes]);
        $errors = [];

        foreach ($statement->fetchAll() as $row) {
            $errors[] = sprintf(
                'Ambiguous: "%s" is already used by %sproject "%s".',
                $row['lookup_key'],
                (int) $row['is_active'] === 1 ? '' : 'inactive ',
                $row['code'],
            );
        }

        if ($errors !== []) {
            throw new ProjectConfigException($errors);
        }
    }
}
