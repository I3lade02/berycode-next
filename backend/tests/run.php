<?php

declare(strict_types=1);

/*
 * Dependency-free test runner for the PHP support backend.
 *
 *   php backend/tests/run.php            all suites (needs the test database)
 *   php backend/tests/run.php --unit     unit tests only (no database)
 *   php backend/tests/run.php Delivery   only test classes/methods matching "Delivery"
 *
 * Database tests fail the run when the test database is unreachable, so a
 * green result always means everything actually ran.
 */

use BeryCode\Support\Tests\Support\AssertionFailed;
use BeryCode\Support\Tests\Support\DbTestCase;
use BeryCode\Support\Tests\Support\Skipped;
use BeryCode\Support\Tests\Support\TestCase;
use BeryCode\Support\Tests\Support\TestDatabase;

require __DIR__ . '/bootstrap.php';

error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false; // silenced with @
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

$args = array_slice($argv, 1);
$unitOnly = in_array('--unit', $args, true);
$filter = null;

foreach ($args as $arg) {
    if (!str_starts_with($arg, '--')) {
        $filter = $arg;
    }
}

$suites = $unitOnly ? ['Unit'] : ['Unit', 'Integration', 'Http'];
$classes = [];

foreach ($suites as $suite) {
    $files = glob(__DIR__ . '/' . $suite . '/*Test.php') ?: [];
    sort($files);

    foreach ($files as $file) {
        require_once $file;
        $classes[] = 'BeryCode\\Support\\Tests\\' . $suite . '\\' . basename($file, '.php');
    }
}

$needsDatabase = false;

foreach ($classes as $class) {
    if (is_subclass_of($class, DbTestCase::class) || (defined($class . '::NEEDS_DATABASE') && constant($class . '::NEEDS_DATABASE'))) {
        $needsDatabase = true;
    }
}

if ($needsDatabase && !TestDatabase::available()) {
    fwrite(STDERR, "\nCannot run database tests: " . TestDatabase::unavailableReason() . "\n");
    fwrite(STDERR, "Start one with `npm run support:test-db` (Docker) or set SUPPORT_TEST_DB_*.\n");
    fwrite(STDERR, "Run `npm run test:support -- --unit` for the database-free tests only.\n\n");
    exit(2);
}

$passed = 0;
$failed = [];
$skipped = [];
$assertions = 0;
$started = microtime(true);

foreach ($classes as $class) {
    $reflection = new ReflectionClass($class);

    if ($reflection->isAbstract() || !$reflection->isSubclassOf(TestCase::class)) {
        continue;
    }

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if (!str_starts_with($method->getName(), 'test')) {
            continue;
        }

        $name = $reflection->getShortName() . '::' . $method->getName();

        if ($filter !== null && stripos($class . '::' . $method->getName(), $filter) === false) {
            continue;
        }

        /** @var TestCase $test */
        $test = new $class();

        try {
            $test->setUp();

            try {
                $test->{$method->getName()}();
            } finally {
                $test->tearDown();
            }

            if ($test->assertions === 0) {
                throw new AssertionFailed('test made no assertions');
            }

            $passed++;
            echo '.';
        } catch (Skipped $skip) {
            $skipped[] = $name . ': ' . $skip->getMessage();
            echo 'S';
        } catch (AssertionFailed $failure) {
            $failed[] = $name . "\n    " . $failure->getMessage() . "\n    at " . self_line($failure);
            echo 'F';
        } catch (Throwable $error) {
            $failed[] = $name . "\n    " . get_class($error) . ': ' . $error->getMessage() . "\n    at " . basename($error->getFile()) . ':' . $error->getLine();
            echo 'E';
        }

        $assertions += $test->assertions;
    }
}

/** Line in the test file where the failing assertion was called. */
function self_line(Throwable $failure): string
{
    foreach ($failure->getTrace() as $frame) {
        if (isset($frame['file']) && !str_contains($frame['file'], '/Support/')) {
            return basename($frame['file']) . ':' . ($frame['line'] ?? '?');
        }
    }

    return (string) $failure->getLine();
}

echo "\n\n";

foreach ($failed as $index => $failure) {
    echo ($index + 1) . ') ' . $failure . "\n\n";
}

foreach ($skipped as $skip) {
    echo 'Skipped: ' . $skip . "\n";
}

printf(
    "%s: %d passed, %d failed, %d skipped, %d assertions (%.2fs, PHP %s)\n",
    $failed === [] ? 'OK' : 'FAILURES',
    $passed,
    count($failed),
    count($skipped),
    $assertions,
    microtime(true) - $started,
    PHP_VERSION,
);

exit($failed === [] && $passed > 0 ? 0 : 1);
