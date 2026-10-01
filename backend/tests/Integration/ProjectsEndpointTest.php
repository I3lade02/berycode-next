<?php

declare(strict_types=1);

namespace BeryCode\Support\Tests\Integration;

use BeryCode\Support\Http\HttpRequest;
use BeryCode\Support\Tests\Support\DbTestCase;

final class ProjectsEndpointTest extends DbTestCase
{
    public function testListsEveryActiveProjectByName(): void
    {
        $response = $this->app->projectsEndpoint()->handle(new HttpRequest('GET', [], ''));

        $this->assertSame(200, $response->status);
        $this->assertSame([
            'ok' => true,
            'projects' => [
                ['code' => 'acme-web', 'name' => 'Acme Web Studio'],
                ['code' => 'nord-shop', 'name' => 'Nordic Shop'],
            ],
        ], $response->json, 'private projects are listed, inactive ones are not');

        $json = (string) json_encode($response->json);
        $this->assertStringNotContainsString('C0ACME0001', $json, 'no channel IDs');
        $this->assertStringNotContainsString('nordic', $json, 'no aliases');
    }

    public function testListedCodesAreAcceptedByTheTicketForm(): void
    {
        $projects = $this->app->projectsEndpoint()->handle(new HttpRequest('GET', [], ''))->json['projects'];

        foreach ($projects as $project) {
            $resolution = $this->app->projectResolver()->resolve($project['code']);
            $this->assertTrue($resolution->isFound(), $project['code']);
        }
    }

    public function testOnlyAnswersGet(): void
    {
        $response = $this->app->projectsEndpoint()->handle(new HttpRequest('POST', [], ''));

        $this->assertSame(405, $response->status);
        $this->assertSame('GET', $response->headers['Allow']);
    }
}
