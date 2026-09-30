<?php

declare(strict_types=1);

namespace BeryCode\Support\Tickets;

enum Priority: string
{
    case NORMAL = 'NORMAL';
    case HIGH = 'HIGH';

    public static function fromInput(string $value): ?self
    {
        return self::tryFrom(strtoupper($value));
    }
}
