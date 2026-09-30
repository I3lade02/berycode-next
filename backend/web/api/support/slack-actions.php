<?php

declare(strict_types=1);

// POST /api/support/slack-actions.php: Slack interactivity Request URL.

use BeryCode\Support\App;
use BeryCode\Support\Endpoints\SlackActionsEndpoint;
use BeryCode\Support\Http\HttpRequest;
use BeryCode\Support\Http\Kernel;

require __DIR__ . '/_app/bootstrap.php';

Kernel::run(
    static fn (App $app, HttpRequest $request) => $app->slackActionsEndpoint()->handle($request),
    SlackActionsEndpoint::MAX_BODY_BYTES,
);
