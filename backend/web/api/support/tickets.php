<?php

declare(strict_types=1);

// POST /api/support/tickets.php: public support request intake.

use BeryCode\Support\App;
use BeryCode\Support\Endpoints\TicketEndpoint;
use BeryCode\Support\Http\HttpRequest;
use BeryCode\Support\Http\Kernel;

require __DIR__ . '/_app/bootstrap.php';

Kernel::run(
    static fn (App $app, HttpRequest $request) => $app->ticketEndpoint()->handle($request),
    TicketEndpoint::MAX_BODY_BYTES,
);
