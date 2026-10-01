<?php

declare(strict_types=1);

// GET /api/support/projects.php: active projects for the support form's list.

use BeryCode\Support\App;
use BeryCode\Support\Http\HttpRequest;
use BeryCode\Support\Http\Kernel;

require __DIR__ . '/_app/bootstrap.php';

Kernel::run(
    static fn (App $app, HttpRequest $request) => $app->projectsEndpoint()->handle($request),
    1024,
);
