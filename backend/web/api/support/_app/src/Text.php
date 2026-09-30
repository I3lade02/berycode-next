<?php

declare(strict_types=1);

namespace BeryCode\Support;

/**
 * Consistent normalisation of untrusted text.
 */
final class Text
{
    /** Bidi overrides/isolates and BOM can visually spoof content in Slack. */
    private const INVISIBLE_CONTROLS = '\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}';

    public static function isValidUtf8(string $value): bool
    {
        return mb_check_encoding($value, 'UTF-8');
    }

    /** NFC normalisation when the intl extension is available (optional on shared hosting). */
    public static function nfc(string $value): string
    {
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);

            if (is_string($normalized)) {
                return $normalized;
            }
        }

        return $value;
    }

    /** One line: controls become spaces, whitespace runs collapse, ends trimmed. */
    public static function singleLine(string $value): string
    {
        $value = self::nfc($value);
        $value = preg_replace('/[\x{0000}-\x{001F}\x{007F}-\x{009F}]/u', ' ', $value) ?? '';
        $value = preg_replace('/[' . self::INVISIBLE_CONTROLS . ']/u', '', $value) ?? '';
        $value = preg_replace('/[\s\p{Z}]+/u', ' ', $value) ?? '';

        return trim($value);
    }

    /** Multi-line: newlines normalised to \n, other controls removed, ends trimmed. */
    public static function multiLine(string $value): string
    {
        $value = self::nfc($value);
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}' . self::INVISIBLE_CONTROLS . ']/u', '', $value) ?? '';
        $value = preg_replace('/[ \t]+$/m', '', $value) ?? '';
        $value = preg_replace("/\n{4,}/", "\n\n\n", $value) ?? '';

        return trim($value);
    }

    public static function length(string $value): int
    {
        return mb_strlen($value, 'UTF-8');
    }

    public static function truncate(string $value, int $max, string $ellipsis = '…'): string
    {
        if (self::length($value) <= $max) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, max(0, $max - self::length($ellipsis)), 'UTF-8')) . $ellipsis;
    }

    /**
     * Splits text into chunks of at most $max characters, preferring paragraph,
     * line and word boundaries.
     *
     * @return list<string>
     */
    public static function chunk(string $value, int $max): array
    {
        $chunks = [];
        $rest = $value;

        while (self::length($rest) > $max) {
            $window = mb_substr($rest, 0, $max, 'UTF-8');
            $cut = null;

            foreach (["\n\n", "\n", ' '] as $separator) {
                $position = mb_strrpos($window, $separator, 0, 'UTF-8');

                if ($position !== false && $position >= (int) ($max * 0.6)) {
                    $cut = $position + self::length($separator);
                    break;
                }
            }

            $cut ??= $max;
            $piece = rtrim(mb_substr($rest, 0, $cut, 'UTF-8'));

            if ($piece !== '') {
                $chunks[] = $piece;
            }

            $rest = ltrim(mb_substr($rest, $cut, null, 'UTF-8'));
        }

        if (trim($rest) !== '') {
            $chunks[] = $rest;
        }

        return $chunks;
    }
}
