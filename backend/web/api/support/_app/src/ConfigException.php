<?php

declare(strict_types=1);

namespace BeryCode\Support;

/**
 * Raised when required configuration is missing or invalid. Messages only ever
 * contain key names, never configured values.
 */
final class ConfigException extends \RuntimeException
{
    /** @param list<string> $missingKeys */
    public static function missing(array $missingKeys): self
    {
        return new self('Missing required configuration: ' . implode(', ', $missingKeys));
    }
}
