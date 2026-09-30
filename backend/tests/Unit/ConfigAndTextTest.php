<?php

declare(strict_types=1);

namespace BeryCode\Support\Tests\Unit;

use BeryCode\Support\App;
use BeryCode\Support\Config;
use BeryCode\Support\ConfigException;
use BeryCode\Support\Database\Migrator;
use BeryCode\Support\Projects\ProjectConfigException;
use BeryCode\Support\Projects\ProjectConfigSync;
use BeryCode\Support\Projects\ProjectKey;
use BeryCode\Support\Tests\Support\TestCase;
use BeryCode\Support\Text;

final class ConfigAndTextTest extends TestCase
{
    public function testDefaultsToProductionAndRejectsUnknownEnvironments(): void
    {
        $this->assertTrue(Config::fromArray([])->isProduction(), 'missing SUPPORT_ENV fails safe to production');
        $this->assertThrows(ConfigException::class, fn () => Config::fromArray(['SUPPORT_ENV' => 'prod'])->env());
        $this->assertThrows(ConfigException::class, fn () => App::fromConfig(Config::fromArray(['SUPPORT_ENV' => 'staging'])));
    }

    public function testDevelopmentMocksAreUnavailableInProduction(): void
    {
        $production = Config::fromArray(['SUPPORT_SLACK_MODE' => 'mock', 'SUPPORT_SLACK_API_BASE_URL' => 'http://127.0.0.1:9/api/']);
        $this->assertThrows(ConfigException::class, fn () => $production->slackMode());
        $this->assertThrows(ConfigException::class, fn () => $production->slackApiBaseUrl());

        $development = Config::fromArray(['SUPPORT_ENV' => 'development', 'SUPPORT_SLACK_MODE' => 'mock']);
        $this->assertSame('mock', $development->slackMode());
        $this->assertSame('https://slack.com/api/', Config::fromArray([])->slackApiBaseUrl());
    }

    public function testProductionProblemsListKeyNamesOnly(): void
    {
        $config = Config::fromArray([
            'SLACK_BOT_TOKEN' => 'xoxp-user-token-secret-value',
            'SUPPORT_HASH_SECRET' => 'short',
            'SUPPORT_CRON_SECRET' => 'also-short',
        ]);
        $problems = implode("\n", $config->productionProblems());

        $this->assertStringContainsString('SLACK_SIGNING_SECRET is not set', $problems);
        $this->assertStringContainsString('SLACK_BOT_TOKEN must be a bot token', $problems);
        $this->assertStringContainsString('SUPPORT_HASH_SECRET must be at least 32', $problems);
        $this->assertStringNotContainsString('xoxp-user-token-secret-value', $problems);
        $this->assertStringNotContainsString('also-short', $problems);
    }

    public function testStaffAllowlistParsing(): void
    {
        $this->assertSame(['U01', 'W02'], Config::fromArray(['SLACK_ALLOWED_STAFF_USER_IDS' => ' U01, W02 U01 '])->staffUserIds());
        $this->assertSame([], Config::fromArray([])->staffUserIds(), 'empty allowlist authorizes nobody');
        $this->assertThrows(ConfigException::class, fn () => Config::fromArray(['SLACK_ALLOWED_STAFF_USER_IDS' => 'jan'])->staffUserIds());
    }

    public function testProjectKeyNormalization(): void
    {
        $this->assertSame('acme website', ProjectKey::normalize("  ACME \t\n Website  "));
        $this->assertSame('kavárna čáp', ProjectKey::normalize('KAVÁRNA   ČÁP'));
        if (class_exists(\Normalizer::class)) {
            $this->assertSame(ProjectKey::normalize("Cafe\u{0301}"), ProjectKey::normalize("Caf\u{00E9}"), 'NFC normalization');
        }

        $this->assertTrue(ProjectKey::isValidCode('acme-web'));
        $this->assertFalse(ProjectKey::isValidCode('a'));
        $this->assertFalse(ProjectKey::isValidCode('-acme'));
        $this->assertTrue(ProjectKey::isValidChannelId('C0ACME0001'));
        $this->assertFalse(ProjectKey::isValidChannelId('#general'));
    }

    public function testProjectConfigRejectsAmbiguityAndBadValues(): void
    {
        $collision = $this->assertThrows(ProjectConfigException::class, fn () => ProjectConfigSync::parse(['projects' => [
            ['code' => 'alpha', 'name' => 'Alpha', 'aliases' => ['shared name'], 'slackChannelId' => 'C0ALPHA001'],
            ['code' => 'beta', 'name' => 'Shared  NAME', 'slackChannelId' => 'C0BETA0001'],
        ]]));
        $this->assertStringContainsString('Ambiguous: "shared name" is used by both "alpha" and "beta"', $collision->getMessage());

        $bad = $this->assertThrows(ProjectConfigException::class, fn () => ProjectConfigSync::parse(['projects' => [
            ['code' => 'Gamma Site', 'name' => 'Gamma', 'slackChannelId' => 'C0GAMMA001'],
            ['code' => 'delta', 'name' => '', 'slackChannelId' => '#delta', 'public' => 'yes'],
        ]]));
        $this->assertStringContainsString('projects[0].code', $bad->getMessage());
        $this->assertStringContainsString('slackChannelId must be a Slack channel ID', $bad->getMessage());
        $this->assertStringContainsString('public must be true or false', $bad->getMessage());

        $this->assertThrows(ProjectConfigException::class, fn () => ProjectConfigSync::parse(['projects' => []]));

        $definitions = ProjectConfigSync::parse(['projects' => [
            ['code' => 'epsilon', 'name' => 'EPSILON', 'aliases' => ['Eps'], 'slackChannelId' => 'C0EPS00001'],
        ]]);
        $this->assertSame(['epsilon' => 'code', 'eps' => 'alias'], $definitions[0]->keys, 'identical name/code stored once');
        $this->assertFalse($definitions[0]->public, 'public defaults to false');
        $this->assertTrue($definitions[0]->active);
    }

    public function testTextChunkingAndMigrationSplitting(): void
    {
        $chunks = Text::chunk(str_repeat('word ', 1000), 2900);
        $this->assertCount(2, $chunks);

        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(2900, Text::length($chunk));
        }

        $this->assertSame(['CREATE TABLE a (x INT)', 'CREATE TABLE b (y INT)'], Migrator::splitStatements("-- comment\nCREATE TABLE a (x INT);\n\nCREATE TABLE b (y INT);\n"));
        $this->assertSame('Abc…', Text::truncate('Abcdef', 4));
    }
}
