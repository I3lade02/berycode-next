<?php

declare(strict_types=1);

namespace BeryCode\Support\Projects;

/**
 * Maps what a customer typed (name, code or alias) to exactly one active
 * project. Never guesses: anything other than a single exact normalized match is
 * rejected. Suggestions only ever come from projects marked public.
 */
final class ProjectResolver
{
    private const MAX_SUGGESTIONS = 3;

    public function __construct(private ProjectRepository $projects)
    {
    }

    public function resolve(string $input): ProjectResolution
    {
        $key = ProjectKey::normalize($input);

        if ($key === '') {
            return ProjectResolution::notFound([]);
        }

        $matches = $this->projects->findActiveByKey($key);

        if (count($matches) === 1) {
            return ProjectResolution::found($matches[0]);
        }

        if (count($matches) > 1) {
            return ProjectResolution::ambiguous();
        }

        return ProjectResolution::notFound($this->suggest($key));
    }

    /** @return list<string> */
    private function suggest(string $needle): array
    {
        $scores = [];
        $needleLength = mb_strlen($needle, 'UTF-8');

        foreach ($this->projects->publicKeys() as $row) {
            $key = $row['lookup_key'];
            $name = $row['display_name'];
            $keyLength = mb_strlen($key, 'UTF-8');
            $score = null;

            if ($needleLength >= 3 && $keyLength >= 3 && (str_contains($key, $needle) || str_contains($needle, $key))) {
                $score = 0;
            } elseif ($needleLength <= 255 && $keyLength <= 255) {
                $distance = levenshtein($needle, $key);

                if ($distance <= max(1, intdiv(max($needleLength, $keyLength), 4))) {
                    $score = $distance;
                }
            }

            if ($score !== null && (!isset($scores[$name]) || $score < $scores[$name])) {
                $scores[$name] = $score;
            }
        }

        asort($scores);

        return array_slice(array_map('strval', array_keys($scores)), 0, self::MAX_SUGGESTIONS);
    }
}
