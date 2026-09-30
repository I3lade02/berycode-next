<?php

declare(strict_types=1);

namespace BeryCode\Support\Http;

use BeryCode\Support\App;
use BeryCode\Support\Config;
use BeryCode\Support\ConfigException;
use BeryCode\Support\Logger;

/**
 * Entry-point runner: builds the app from configuration, handles the request,
 * sends the response, then runs deferred work (such as the first Slack delivery
 * attempt) after the client has its answer.
 *
 * Deferred work is an optimisation only: its state is already durable in the
 * database and the cron job retries anything that does not finish.
 */
final class Kernel
{
    /**
     * @param callable(App, HttpRequest): HttpResponse $handler
     */
    public static function run(callable $handler, int $maxBodyBytes): void
    {
        $logger = new Logger();

        try {
            $request = HttpRequest::fromGlobals($maxBodyBytes);
            $app = App::fromConfig(Config::load(), $logger);
            $response = $handler($app, $request);
        } catch (ConfigException $exception) {
            $logger->error('configuration_error', Logger::exceptionContext($exception));
            $response = HttpResponse::json(503, ['ok' => false, 'error' => 'unavailable']);
        } catch (\PDOException $exception) {
            $logger->error('database_error', Logger::exceptionContext($exception));
            $response = HttpResponse::json(503, ['ok' => false, 'error' => 'unavailable']);
        } catch (\Throwable $exception) {
            $logger->error('unhandled_error', Logger::exceptionContext($exception));
            $response = HttpResponse::json(500, ['ok' => false, 'error' => 'server_error']);
        }

        self::send($response);

        foreach ($response->deferred as $task) {
            try {
                $task();
            } catch (\Throwable $exception) {
                $logger->error('deferred_task_failed', Logger::exceptionContext($exception));
            }
        }
    }

    public static function send(HttpResponse $response): void
    {
        $body = $response->json === null
            ? ''
            : (string) json_encode($response->json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (PHP_SAPI === 'cli') {
            echo $body === '' ? '' : $body . PHP_EOL;

            return;
        }

        $hasDeferred = $response->deferred !== [];

        if ($hasDeferred) {
            ignore_user_abort(true);
            @set_time_limit(60);
        }

        http_response_code($response->status);
        header('Content-Type: ' . ($response->json === null ? 'text/plain' : 'application/json') . '; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex');
        header('Content-Length: ' . strlen($body));

        foreach ($response->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        if ($hasDeferred) {
            header('Connection: close');
        }

        echo $body;

        if (!$hasDeferred) {
            return;
        }

        // Hand the response to the client now and keep working in this process.
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } else {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            flush();
        }
    }
}
