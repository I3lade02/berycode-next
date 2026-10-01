<?php

declare(strict_types=1);

namespace BeryCode\Support\Endpoints;

use BeryCode\Support\App;
use BeryCode\Support\Http\HttpRequest;
use BeryCode\Support\Http\HttpResponse;

/**
 * GET /api/support/projects.php — the active projects customers choose from in
 * the support form. Codes and display names only; never channel IDs, aliases
 * or flags.
 */
final class ProjectsEndpoint
{
    public function __construct(private App $app)
    {
    }

    public function handle(HttpRequest $request): HttpResponse
    {
        if ($request->method !== 'GET') {
            return HttpResponse::json(405, ['ok' => false, 'error' => 'method_not_allowed'], ['Allow' => 'GET']);
        }

        return HttpResponse::json(200, ['ok' => true, 'projects' => $this->app->projects()->listActive()]);
    }
}
