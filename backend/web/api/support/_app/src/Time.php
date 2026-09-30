<?php

declare(strict_types=1);

namespace BeryCode\Support;

final class Time
{
    /** Format for DATETIME(3) columns (always UTC). */
    public static function db(\DateTimeImmutable $time): string
    {
        return $time->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }

    public static function fromDb(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }

    public static function plusSeconds(\DateTimeImmutable $time, int $seconds): \DateTimeImmutable
    {
        return $time->modify(sprintf('%+d seconds', $seconds));
    }
}
