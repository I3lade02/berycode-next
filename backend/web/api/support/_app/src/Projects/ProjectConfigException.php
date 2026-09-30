<?php

declare(strict_types=1);

namespace BeryCode\Support\Projects;

final class ProjectConfigException extends \RuntimeException
{
    /** @param list<string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode("\n", $errors));
    }
}
