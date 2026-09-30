<?php

declare(strict_types=1);

namespace BeryCode\Support\Tests\Integration;

use BeryCode\Support\Projects\ProjectConfigException;
use BeryCode\Support\Projects\ProjectConfigSync;
use BeryCode\Support\Projects\ProjectResolution;
use BeryCode\Support\Tests\Support\DbTestCase;

final class ProjectRoutingTest extends DbTestCase
{
    public function testResolvesCodeNameAndAliasesWithNormalization(): void
    {
        $resolver = $this->app->projectResolver();

        foreach (['acme-web', 'ACME-WEB', 'Acme Web Studio', "  acme   web\tstudio ", 'acme', 'Acme  website'] as $input) {
            $resolution = $resolver->resolve($input);
            $this->assertTrue($resolution->isFound(), $input);
            $this->assertSame('acme-web', $resolution->project->code, $input);
            $this->assertSame('C0ACME0001', $resolution->project->slackChannelId, $input);
        }

        $this->assertSame('G0NORD0001', $resolver->resolve('Nordic')->project->slackChannelId, 'private channel project');
    }

    public function testRejectsUnknownAndInactiveProjectsWithoutGuessing(): void
    {
        $resolver = $this->app->projectResolver();

        $this->assertSame(ProjectResolution::NOT_FOUND, $resolver->resolve('acme-we')->status, 'no prefix/fuzzy matching');
        $this->assertSame(ProjectResolution::NOT_FOUND, $resolver->resolve('old-site')->status, 'inactive projects are unknown');
        $this->assertSame(ProjectResolution::NOT_FOUND, $resolver->resolve('   ')->status);
    }

    public function testSuggestsOnlyPublicActiveProjects(): void
    {
        $resolver = $this->app->projectResolver();

        $this->assertSame(['Acme Web Studio'], $resolver->resolve('acme-wbe')->suggestions);
        $this->assertSame([], $resolver->resolve('nordic shp')->suggestions, 'private project is never suggested');
        $this->assertSame([], $resolver->resolve('old sit')->suggestions, 'inactive project is never suggested');
        $this->assertSame([], $resolver->resolve('zzzz')->suggestions);
    }

    public function testConfigurationCannotCreateAmbiguousKeys(): void
    {
        $sync = $this->app->projectConfigSync();

        $error = $this->assertThrows(ProjectConfigException::class, fn () => $sync->apply(ProjectConfigSync::parse(['projects' => [
            ['code' => 'newcomer', 'name' => 'Newcomer', 'aliases' => ['ACME'], 'slackChannelId' => 'C0NEW00001'],
        ]])));
        $this->assertStringContainsString('"acme" is already used by project "acme-web"', $error->getMessage());

        $error = $this->assertThrows(ProjectConfigException::class, fn () => $sync->apply(ProjectConfigSync::parse(['projects' => [
            ['code' => 'reuse-old', 'name' => 'Old Site', 'slackChannelId' => 'C0NEW00002'],
        ]])));
        $this->assertStringContainsString('inactive project "old-site"', $error->getMessage());

        $this->assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM support_projects WHERE code IN ('newcomer', 'reuse-old')")->fetchColumn(), 'nothing written');

        // Even bypassing the command, the database refuses a duplicate key.
        $this->assertThrows(\PDOException::class, fn () => $this->pdo->exec(
            "INSERT INTO support_project_keys (lookup_key, project_id, key_type) SELECT 'nordic', id, 'alias' FROM support_projects WHERE code = 'acme-web'",
        ));
    }

    public function testKeysCanMoveBetweenProjectsInOneFileAndDryRunWritesNothing(): void
    {
        $sync = $this->app->projectConfigSync();
        $definitions = ProjectConfigSync::parse(['projects' => [
            ['code' => 'acme-web', 'name' => 'Acme Web Studio', 'aliases' => [], 'slackChannelId' => 'C0ACME0001', 'public' => true],
            ['code' => 'nord-shop', 'name' => 'Nordic Shop', 'aliases' => ['nordic', 'acme'], 'slackChannelId' => 'G0NORD0001'],
        ]]);

        $report = $sync->apply($definitions, false, true);
        $this->assertStringContainsString('Dry run', implode("\n", $report));
        $this->assertSame('acme-web', $this->app->projectResolver()->resolve('acme')->project->code);

        $sync->apply($definitions);
        $this->assertSame('nord-shop', $this->app->projectResolver()->resolve('acme')->project->code);
    }

    public function testChannelChangeDoesNotMoveExistingTickets(): void
    {
        $response = $this->submit($this->validPayload());
        $this->assertSame(201, $response->status);

        $report = $this->app->projectConfigSync()->apply(ProjectConfigSync::parse(['projects' => [
            ['code' => 'acme-web', 'name' => 'Acme Web Studio', 'aliases' => ['acme'], 'slackChannelId' => 'C0ACMENEW1', 'public' => true],
        ]]));
        $this->assertStringContainsString('existing tickets keep their original channel', implode("\n", $report));

        $this->runDeferred($response);
        $this->assertSame('C0ACME0001', $this->slack->calls('chat.postMessage')[0]['payload']['channel']);

        $second = $this->submit($this->validPayload());
        $this->runDeferred($second);
        $this->assertSame('C0ACMENEW1', $this->slack->calls('chat.postMessage')[1]['payload']['channel']);
    }

    public function testPrintedSqlAppliesTheSameConfigurationSafely(): void
    {
        $run = function (string $sql): void {
            foreach (\BeryCode\Support\Database\Migrator::splitStatements($sql) as $statement) {
                $this->pdo->exec($statement);
            }
        };
        $definitions = static fn (string $channel) => ProjectConfigSync::parse(['projects' => [
            ['code' => 'o-reilly', 'name' => "O'Reilly \\ Partners", 'aliases' => ["it's ours"], 'slackChannelId' => $channel, 'public' => true],
            ['code' => 'acme-web', 'name' => 'Acme Web Studio', 'aliases' => ['acme'], 'slackChannelId' => 'C0ACME0001'],
        ]]);

        $run(ProjectConfigSync::toSql($definitions('C0OREILLY1')));
        $run(ProjectConfigSync::toSql($definitions('C0OREILLY2')));

        $resolver = $this->app->projectResolver();
        $this->assertSame('C0OREILLY2', $resolver->resolve("o'reilly \\ partners")->project->slackChannelId, 'quotes and backslashes survive; re-run updates');
        $this->assertSame('o-reilly', $resolver->resolve("IT'S OURS")->project->code);
        $this->assertSame(ProjectResolution::NOT_FOUND, $resolver->resolve('acme website')->status, 'keys replaced for listed projects');
        $this->assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM support_projects WHERE code = 'o-reilly'")->fetchColumn());

        // A collision with a project not in the file aborts without partial changes.
        $collision = ProjectConfigSync::toSql(ProjectConfigSync::parse(['projects' => [
            ['code' => 'newcomer', 'name' => 'Newcomer', 'aliases' => ['nordic'], 'slackChannelId' => 'C0NEW00001'],
        ]]));
        $this->assertThrows(\PDOException::class, fn () => $run($collision));
        $this->pdo->exec('ROLLBACK');
        $this->assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM support_projects WHERE code = 'newcomer'")->fetchColumn());
        $this->assertSame('nord-shop', $resolver->resolve('nordic')->project->code);
    }

    public function testDeactivatingMissingProjects(): void
    {
        $this->app->projectConfigSync()->apply(ProjectConfigSync::parse(['projects' => [
            ['code' => 'acme-web', 'name' => 'Acme Web Studio', 'aliases' => ['acme'], 'slackChannelId' => 'C0ACME0001'],
        ]]), true);

        $this->assertSame(ProjectResolution::NOT_FOUND, $this->app->projectResolver()->resolve('nordic')->status);
        $this->assertTrue($this->app->projectResolver()->resolve('acme')->isFound());
    }
}
