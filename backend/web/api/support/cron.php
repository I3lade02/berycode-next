<?php

declare(strict_types=1);

// Delivery retry job. HTTP: GET/POST with SUPPORT_CRON_SECRET. CLI: php cron.php

use BeryCode\Support\App;
use BeryCode\Support\Http\HttpRequest;
use BeryCode\Support\Http\Kernel;

require __DIR__ . '/_app/bootstrap.php';

Kernel::run(
    static fn (App $app, HttpRequest $request) => $app->cronEndpoint()->handle($request),
    1024,
);
