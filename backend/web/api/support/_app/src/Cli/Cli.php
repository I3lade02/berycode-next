<?php

declare(strict_types=1);

namespace BeryCode\Support\Cli;

use BeryCode\Support\App;
use BeryCode\Support\Config;
use BeryCode\Support\ConfigException;
use BeryCode\Support\Database\Migrator;
use BeryCode\Support\Logger;
use BeryCode\Support\Projects\ProjectConfigException;
use BeryCode\Support\Projects\ProjectConfigSync;
use BeryCode\Support\Time;

/**
 * Operator commands: `npm run support -- <command>` (or php backend/bin/support.php).
 * Output never includes secrets or customer-submitted content.
 */
final class Cli
{
    private const USAGE = <<<'TXT'
Usage: npm run support -- <command> [options] [--config=file.php | --env-file=path]

  migrate                                Apply database migrations
  projects:sync <file.json> [--dry-run] [--deactivate-missing]
                                         Create/update projects and their Slack channels
  projects:sync <file.json> --print-sql  Print the same change as SQL for phpMyAdmin (no DB needed)
  projects:list                          List configured projects
  seed:demo                              Load fictional demo projects (refused in production)
  deliveries:run [--limit=25]            Run due Slack deliveries and message syncs (the retry job)
  deliveries:requeue --ticket=BC-000123  Retry a FAILED delivery/sync after fixing its cause
  deliveries:requeue --all-failed        Retry every FAILED delivery/sync
  status                                 Delivery/sync counts and recent problems
  config:check                           Validate configuration (prints key names only)
  slack:check                            Call Slack auth.test (sends no message)

Configuration:
  --config=berycode-support-config.php   use the production PHP config file (the one you upload);
                                         .env files are then not read
  --env-file=path                        read KEY=VALUE variables from this file only
  (default)                              .env.local, then .env in the repo root
  Real environment variables always win, e.g. SUPPORT_DB_HOST=<remote host> for remote MySQL.
TXT;

    /** @param list<string> $argv */
    public static function main(array $argv, string $repoRoot): int
    {
        array_shift($argv);
        $options = [];
        $arguments = [];

        foreach ($argv as $arg) {
            if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $matches)) {
                $options[$matches[1]] = $matches[2] ?? true;
            } else {
                $arguments[] = $arg;
            }
        }

        $envFile = $options['env-file'] ?? null;
        $configFile = $options['config'] ?? null;

        if (is_string($configFile)) {
            $path = realpath($configFile);

            if ($path === false || !is_file($path)) {
                fwrite(STDERR, "Cannot read config file {$configFile}\n");

                return 1;
            }

            // Config::load() reads this file first; .env files are deliberately skipped
            // so local development values can never redirect production commands.
            putenv('SUPPORT_CONFIG_FILE=' . $path);

            if (is_string($envFile) && !DotEnv::load($envFile)) {
                fwrite(STDERR, "Cannot read env file {$envFile}\n");

                return 1;
            }
        } elseif (is_string($envFile)) {
            if (!DotEnv::load($envFile)) {
                fwrite(STDERR, "Cannot read env file {$envFile}\n");

                return 1;
            }
        } else {
            DotEnv::load($repoRoot . '/.env.local');
            DotEnv::load($repoRoot . '/.env');
        }

        $command = array_shift($arguments);

        if ($command === null || $command === 'help' || isset($options['help'])) {
            fwrite(STDOUT, self::USAGE . "\n");

            return $command === null ? 1 : 0;
        }

        try {
            $config = Config::load();
            $app = App::fromConfig($config, new Logger(static function (string $line): void {
                fwrite(STDERR, $line . "\n");
            }));

            return (new self($app, $repoRoot))->run($command, $arguments, $options);
        } catch (ConfigException $exception) {
            fwrite(STDERR, 'Configuration error: ' . $exception->getMessage() . "\n");

            return 2;
        } catch (ProjectConfigException $exception) {
            fwrite(STDERR, "Project configuration rejected:\n  - " . implode("\n  - ", $exception->errors) . "\n");

            return 3;
        } catch (\PDOException $exception) {
            $context = Logger::exceptionContext($exception);
            fwrite(STDERR, 'Database error (' . $context['code'] . '). Check SUPPORT_DB_* settings and that migrations ran.' . "\n");

            return 4;
        }
    }

    public function __construct(private App $app, private string $repoRoot)
    {
    }

    /**
     * @param list<string> $arguments
     * @param array<string, string|true> $options
     */
    public function run(string $command, array $arguments, array $options): int
    {
        switch ($command) {
            case 'migrate':
                $applied = (new Migrator($this->app->pdo(), $this->repoRoot . '/backend/migrations'))
                    ->migrate(fn (string $line) => $this->out($line));
                $this->out($applied === [] ? 'Database is up to date.' : 'Done.');

                return 0;

            case 'projects:sync':
                $file = $arguments[0] ?? null;

                if ($file === null) {
                    $this->err('Usage: projects:sync <file.json> [--dry-run] [--deactivate-missing]');

                    return 1;
                }

                return $this->syncProjects($file, isset($options['dry-run']), isset($options['deactivate-missing']), isset($options['print-sql']));

            case 'seed:demo':
                if ($this->app->config->isProduction()) {
                    $this->err('Refusing to load demo projects while SUPPORT_ENV=production.');

                    return 1;
                }

                return $this->syncProjects($this->repoRoot . '/backend/config/projects.example.json', false, false);

            case 'projects:list':
                foreach ($this->app->projects()->listAll() as $row) {
                    $this->out(sprintf(
                        '%-24s %-12s %-8s %-7s %s%s',
                        $row['code'],
                        $row['slack_channel_id'],
                        (int) $row['is_active'] === 1 ? 'active' : 'inactive',
                        (int) $row['is_public'] === 1 ? 'public' : 'private',
                        $row['display_name'],
                        $row['aliases'] ? '  [aliases: ' . $row['aliases'] . ']' : '',
                    ));
                }

                return 0;

            case 'deliveries:run':
                $limit = isset($options['limit']) && is_string($options['limit']) ? max(1, (int) $options['limit']) : 25;
                $summary = $this->app->delivery()->runDue($limit, microtime(true) + 300);
                $summary['rate_limit_rows_purged'] = $this->app->rateLimiter()->purgeExpired();
                $this->out((string) json_encode($summary));

                return 0;

            case 'deliveries:requeue':
                return $this->requeue($options);

            case 'status':
                return $this->status();

            case 'config:check':
                $problems = $this->app->config->productionProblems();
                $this->out('SUPPORT_ENV=' . $this->app->config->env());

                if ($problems === []) {
                    $this->out('Configuration looks complete for production.');

                    return 0;
                }

                foreach ($problems as $problem) {
                    $this->out('  - ' . $problem);
                }

                return 1;

            case 'slack:check':
                $response = $this->app->slackClient()->call('auth.test', []);

                if (!$response->ok) {
                    $this->err('auth.test failed: ' . $response->errorSummary());

                    return 1;
                }

                $team = (string) ($response->data['team_id'] ?? '');
                $this->out('Authenticated as bot user ' . ($response->data['user_id'] ?? '?') . ' in workspace ' . $team);

                if ($team !== (string) $this->app->config->slackTeamId()) {
                    $this->err('SLACK_TEAM_ID does not match the token\'s workspace (' . $team . ').');

                    return 1;
                }

                $this->out('SLACK_TEAM_ID matches. No message was sent.');

                return 0;

            default:
                $this->err('Unknown command: ' . $command);
                $this->err(self::USAGE);

                return 1;
        }
    }

    private function syncProjects(string $file, bool $dryRun, bool $deactivateMissing, bool $printSql = false): int
    {
        $raw = @file_get_contents($file);

        if ($raw === false) {
            $this->err('Cannot read ' . $file);

            return 1;
        }

        $json = json_decode($raw, true);

        if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->err('Invalid JSON in ' . $file . ': ' . json_last_error_msg());

            return 1;
        }

        $definitions = ProjectConfigSync::parse($json);

        if ($printSql) {
            fwrite(STDOUT, ProjectConfigSync::toSql($definitions, $deactivateMissing));

            return 0;
        }

        foreach ($this->app->projectConfigSync()->apply($definitions, $deactivateMissing, $dryRun) as $line) {
            $this->out($line);
        }

        return 0;
    }

    /** @param array<string, string|true> $options */
    private function requeue(array $options): int
    {
        $tickets = $this->app->tickets();
        $now = Time::db($this->app->clock()->now());
        $ticketId = null;

        if (isset($options['ticket']) && is_string($options['ticket'])) {
            $ticketId = $tickets->idByReference($options['ticket']);

            if ($ticketId === null) {
                $this->err('Unknown ticket ' . $options['ticket']);

                return 1;
            }
        } elseif (!isset($options['all-failed'])) {
            $this->err('Pass --ticket=BC-000123 or --all-failed.');

            return 1;
        }

        $deliveries = $tickets->requeueFailedDeliveries($ticketId, $now);
        $syncs = $tickets->requeueFailedSyncs($ticketId, $now);
        $this->out(sprintf('Requeued %d delivery(ies) and %d message sync(s). They run on the next cron/deliveries:run.', $deliveries, $syncs));

        return 0;
    }

    private function status(): int
    {
        $tickets = $this->app->tickets();
        $counts = $tickets->counts();

        foreach ($counts as $group => $values) {
            $this->out(str_pad($group, 9) . ' ' . ($values === [] ? '-' : http_build_query($values, '', '  ')));
        }

        $problems = $tickets->problems();

        if ($problems !== []) {
            $this->out('');
            $this->out('Tickets needing attention (newest first):');

            foreach ($problems as $row) {
                $this->out(sprintf(
                    '  %-10s delivery=%s (attempts %d%s) sync=%s%s',
                    $row['reference'],
                    $row['delivery_state'],
                    $row['delivery_attempts'],
                    $row['delivery_last_error'] ? ', ' . $row['delivery_last_error'] : '',
                    $row['sync_state'],
                    $row['sync_last_error'] ? ' (' . $row['sync_last_error'] . ')' : '',
                ));
            }
        }

        return 0;
    }

    private function out(string $line): void
    {
        fwrite(STDOUT, $line . "\n");
    }

    private function err(string $line): void
    {
        fwrite(STDERR, $line . "\n");
    }
}
