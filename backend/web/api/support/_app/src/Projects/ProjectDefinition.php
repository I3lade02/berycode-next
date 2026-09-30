<?php

declare(strict_types=1);

namespace BeryCode\Support\Projects;

final class ProjectDefinition
{
    /**
     * @param list<string> $aliases
     * @param array<string, 'code'|'name'|'alias'> $keys normalized key => type
     */
    public function __construct(
        public readonly string $code,
        public readonly string $displayName,
        public readonly array $aliases,
        public readonly array $keys,
        public readonly string $slackChannelId,
        public readonly bool $active,
        public readonly bool $public,
    ) {
    }
}
