<?php

declare(strict_types=1);

namespace BeryCode\Support\Projects;

final class Project
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $displayName,
        public readonly string $slackChannelId,
        public readonly bool $isActive,
        public readonly bool $isPublic,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['code'],
            (string) $row['display_name'],
            (string) $row['slack_channel_id'],
            (bool) $row['is_active'],
            (bool) $row['is_public'],
        );
    }
}
