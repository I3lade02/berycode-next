<?php

declare(strict_types=1);

namespace BeryCode\Support\Projects;

use BeryCode\Support\Text;

/**
 * The one normalisation used both when storing project codes/names/aliases and
 * when resolving what a customer typed: Unicode NFC, collapsed whitespace,
 * trimmed, lower case.
 */
final class ProjectKey
{
    public const MAX_LENGTH = 150;

    public static function normalize(string $value): string
    {
        return mb_strtolower(Text::singleLine($value), 'UTF-8');
    }

    public static function isValidCode(string $normalizedCode): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9._-]{0,62}[a-z0-9]$/', $normalizedCode);
    }

    public static function isValidChannelId(string $channelId): bool
    {
        // Public channels start with C, legacy private channels with G.
        return (bool) preg_match('/^[CG][A-Z0-9]{8,20}$/', $channelId);
    }
}
