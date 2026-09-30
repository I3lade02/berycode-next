<?php

declare(strict_types=1);

namespace BeryCode\Support\Tickets;

enum RequestType: string
{
    case BUG = 'BUG';
    case CHANGE_REQUEST = 'CHANGE_REQUEST';
    case OTHER = 'OTHER';

    /** Accepts the lower-case values sent by the form ("bug", "change_request", "other"). */
    public static function fromInput(string $value): ?self
    {
        return self::tryFrom(strtoupper($value));
    }
}
