<?php

declare(strict_types=1);

namespace BeryCode\Support;

use BeryCode\Support\Database\Database;
use BeryCode\Support\Delivery\DeliveryService;
use BeryCode\Support\Endpoints\CronEndpoint;
use BeryCode\Support\Endpoints\SlackActionsEndpoint;
use BeryCode\Support\Endpoints\TicketEndpoint;
use BeryCode\Support\Projects\ProjectConfigSync;
use BeryCode\Support\Projects\ProjectRepository;
use BeryCode\Support\Projects\ProjectResolver;
use BeryCode\Support\Security\RateLimiter;
use BeryCode\Support\Slack\CurlTransport;
use BeryCode\Support\Slack\MockTransport;
use BeryCode\Support\Slack\SlackClient;
use BeryCode\Support\Slack\SlackMessageBuilder;
use BeryCode\Support\Slack\SlackTransport;
use BeryCode\Support\Tickets\TicketRepository;
use BeryCode\Support\Tickets\TicketService;
use PDO;

/**
 * Small hand-wired service container. Everything is created lazily, so an
 * endpoint only needs the configuration it actually uses.
 */
final class App
{
    private ?PDO $pdo;
    private ?SlackClient $slackClient = null;

    public function __construct(
        public readonly Config $config,
        private Clock $clock,
        private Logger $logger,
        ?PDO $pdo = null,
        private ?SlackTransport $transport = null,
    ) {
        $this->pdo = $pdo;
    }

    public static function fromConfig(Config $config, ?Logger $logger = null): self
    {
        // Validates SUPPORT_ENV (and fails closed on typos) before anything runs.
        $config->env();

        return new self($config, new SystemClock(), $logger ?? new Logger());
    }

    public function pdo(): PDO
    {
        return $this->pdo ??= Database::connect($this->config);
    }

    public function clock(): Clock
    {
        return $this->clock;
    }

    public function logger(): Logger
    {
        return $this->logger;
    }

    public function slackClient(): SlackClient
    {
        if ($this->slackClient !== null) {
            return $this->slackClient;
        }

        $baseUrl = $this->config->slackApiBaseUrl();
        $transport = $this->transport;

        if ($transport === null) {
            $transport = $this->config->slackMode() === 'mock'
                ? new MockTransport((string) $this->config->get('SUPPORT_MOCK_SLACK_LOG', sys_get_temp_dir() . '/berycode-support-mock-slack.log'))
                : new CurlTransport();
        }

        $token = $this->config->slackBotToken();

        if ($this->config->slackMode() === 'mock') {
            $token ??= 'xoxb-mock';
        }

        $responsePrefixes = ['https://hooks.slack.com/'];

        if (!$this->config->isProduction() && $baseUrl !== Config::DEFAULT_SLACK_API_BASE_URL) {
            // The local fake Slack used by the HTTP tests also receives response_url posts.
            $responsePrefixes[] = $baseUrl;
        }

        return $this->slackClient = new SlackClient($transport, $token, $baseUrl, $this->config->slackTimeoutSeconds(), $responsePrefixes);
    }

    public function tickets(): TicketRepository
    {
        return new TicketRepository($this->pdo());
    }

    public function projects(): ProjectRepository
    {
        return new ProjectRepository($this->pdo());
    }

    public function projectResolver(): ProjectResolver
    {
        return new ProjectResolver($this->projects());
    }

    public function projectConfigSync(): ProjectConfigSync
    {
        return new ProjectConfigSync($this->pdo(), $this->clock);
    }

    public function ticketService(): TicketService
    {
        return new TicketService($this->pdo(), $this->tickets(), $this->clock, $this->logger, $this->config->ticketPrefix());
    }

    public function rateLimiter(): RateLimiter
    {
        return new RateLimiter($this->pdo(), $this->clock, $this->config->hashSecret());
    }

    public function messageBuilder(): SlackMessageBuilder
    {
        return new SlackMessageBuilder($this->config->slackLocale());
    }

    public function delivery(): DeliveryService
    {
        return new DeliveryService($this->tickets(), $this->slackClient(), $this->messageBuilder(), $this->clock, $this->logger);
    }

    public function ticketEndpoint(): TicketEndpoint
    {
        return new TicketEndpoint($this);
    }

    public function slackActionsEndpoint(): SlackActionsEndpoint
    {
        return new SlackActionsEndpoint($this);
    }

    public function cronEndpoint(): CronEndpoint
    {
        return new CronEndpoint($this);
    }
}
