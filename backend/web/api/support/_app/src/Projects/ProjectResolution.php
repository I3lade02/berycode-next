<?php

declare(strict_types=1);

namespace BeryCode\Support\Projects;

final class ProjectResolution
{
    public const FOUND = 'found';
    public const NOT_FOUND = 'project_not_found';
    public const AMBIGUOUS = 'project_ambiguous';

    /** @param list<string> $suggestions display names of public projects only */
    private function __construct(
        public readonly string $status,
        public readonly ?Project $project,
        public readonly array $suggestions,
    ) {
    }

    public static function found(Project $project): self
    {
        return new self(self::FOUND, $project, []);
    }

    /** @param list<string> $suggestions */
    public static function notFound(array $suggestions): self
    {
        return new self(self::NOT_FOUND, null, $suggestions);
    }

    public static function ambiguous(): self
    {
        return new self(self::AMBIGUOUS, null, []);
    }

    public function isFound(): bool
    {
        return $this->status === self::FOUND;
    }
}
