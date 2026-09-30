<?php

declare(strict_types=1);

namespace BeryCode\Support\Tickets;

use BeryCode\Support\Text;

/**
 * Server-side validation of the public form. Authoritative: the browser checks
 * the same limits (src/lib/support/schema.ts) only for a nicer experience.
 *
 * Errors are stable codes keyed by field: "name", "email", "project", "issues"
 * (the list itself) and "issues.<index>.<field>" for one issue. The frontend
 * maps them to Czech/English messages.
 */
final class TicketValidator
{
    public const NAME_MIN = 2;
    public const NAME_MAX = 100;
    public const EMAIL_MAX = 254;
    public const PROJECT_MAX = 100;
    public const SUBJECT_MIN = 5;
    public const SUBJECT_MAX = 150;
    public const DESCRIPTION_MIN = 20;
    public const DESCRIPTION_MAX = 5000;
    public const MAX_ISSUES = 10;

    /**
     * @param array<mixed> $data decoded JSON body
     * @return array{0: ?TicketSubmission, 1: array<string, string>} submission or field => error code
     */
    public static function validate(array $data): array
    {
        $errors = [];

        $name = self::singleLine($data['name'] ?? null, 'name', self::NAME_MIN, self::NAME_MAX, $errors);
        $project = self::singleLine($data['project'] ?? null, 'project', 1, self::PROJECT_MAX, $errors);
        $email = self::email($data['email'] ?? null, $errors);
        $issues = self::issues($data['issues'] ?? null, $errors);

        $locale = ($data['locale'] ?? 'cs') === 'en' ? 'en' : 'cs';
        $key = $data['idempotencyKey'] ?? null;

        if (!is_string($key) || !preg_match('/^[A-Za-z0-9-]{16,64}$/', $key)) {
            $errors['idempotencyKey'] = 'invalid';
        }

        if ($errors !== [] || $issues === []) {
            return [null, $errors];
        }

        return [
            new TicketSubmission((string) $name, (string) $email, (string) $project, $issues, $locale, (string) $key),
            [],
        ];
    }

    /**
     * @param array<string, string> $errors
     * @return list<IssueSubmission>
     */
    private static function issues(mixed $raw, array &$errors): array
    {
        if (!is_array($raw) || ($raw !== [] && !array_is_list($raw))) {
            $errors['issues'] = 'invalid';

            return [];
        }

        if ($raw === []) {
            $errors['issues'] = 'required';

            return [];
        }

        if (count($raw) > self::MAX_ISSUES) {
            $errors['issues'] = 'too_many';

            return [];
        }

        $issues = [];

        foreach ($raw as $index => $item) {
            $prefix = 'issues.' . $index . '.';

            if (!is_array($item)) {
                $errors[$prefix . 'subject'] = 'required';
                continue;
            }

            $subject = self::singleLine($item['subject'] ?? null, $prefix . 'subject', self::SUBJECT_MIN, self::SUBJECT_MAX, $errors);
            $description = self::multiLine($item['description'] ?? null, $prefix . 'description', $errors);

            $requestType = null;
            $rawType = $item['requestType'] ?? null;

            if (!is_string($rawType) || $rawType === '') {
                $errors[$prefix . 'requestType'] = 'required';
            } elseif (($requestType = RequestType::fromInput($rawType)) === null) {
                $errors[$prefix . 'requestType'] = 'invalid';
            }

            $priority = Priority::NORMAL;
            $rawPriority = $item['priority'] ?? null;

            if ($rawPriority !== null && $rawPriority !== '') {
                $parsed = is_string($rawPriority) ? Priority::fromInput($rawPriority) : null;

                if ($parsed === null) {
                    $errors[$prefix . 'priority'] = 'invalid';
                } else {
                    $priority = $parsed;
                }
            }

            if ($subject !== null && $description !== null && $requestType !== null) {
                $issues[] = new IssueSubmission($requestType, $priority, $subject, $description);
            }
        }

        return count($issues) === count($raw) ? $issues : [];
    }

    /** @param array<string, string> $errors */
    private static function email(mixed $raw, array &$errors): ?string
    {
        if (!is_string($raw) || trim($raw) === '') {
            $errors['email'] = 'required';

            return null;
        }

        if (!Text::isValidUtf8($raw)) {
            $errors['email'] = 'invalid';

            return null;
        }

        $candidate = trim($raw);

        if (strlen($candidate) > self::EMAIL_MAX) {
            $errors['email'] = 'too_long';
        } elseif (preg_match('/[\x00-\x20\x7F]/', $candidate) || filter_var($candidate, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'invalid';
        } else {
            return $candidate;
        }

        return null;
    }

    /** @param array<string, string> $errors */
    private static function singleLine(mixed $raw, string $key, int $min, int $max, array &$errors): ?string
    {
        if (!is_string($raw) || trim($raw) === '') {
            $errors[$key] = 'required';

            return null;
        }

        if (strlen($raw) > $max * 8) {
            $errors[$key] = 'too_long';

            return null;
        }

        if (!Text::isValidUtf8($raw)) {
            $errors[$key] = 'invalid';

            return null;
        }

        $value = Text::singleLine($raw);

        return self::bounded($value, $key, $min, $max, $errors);
    }

    /** @param array<string, string> $errors */
    private static function multiLine(mixed $raw, string $key, array &$errors): ?string
    {
        if (!is_string($raw) || trim($raw) === '') {
            $errors[$key] = 'required';

            return null;
        }

        if (!Text::isValidUtf8($raw)) {
            $errors[$key] = 'invalid';

            return null;
        }

        return self::bounded(Text::multiLine($raw), $key, self::DESCRIPTION_MIN, self::DESCRIPTION_MAX, $errors);
    }

    /** @param array<string, string> $errors */
    private static function bounded(string $value, string $key, int $min, int $max, array &$errors): ?string
    {
        $length = Text::length($value);

        if ($length === 0) {
            $errors[$key] = 'required';
        } elseif ($length < $min) {
            $errors[$key] = 'too_short';
        } elseif ($length > $max) {
            $errors[$key] = 'too_long';
        } else {
            return $value;
        }

        return null;
    }
}
