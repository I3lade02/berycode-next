<?php

declare(strict_types=1);

namespace BeryCode\Support\Tests\Support;

/**
 * Minimal assertion base class for the dependency-free test runner (run.php).
 * Test methods are public and start with "test".
 */
abstract class TestCase
{
    public int $assertions = 0;

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;

        if ($expected !== $actual) {
            throw new AssertionFailed(($message !== '' ? $message . ': ' : '') . 'expected ' . self::export($expected) . ', got ' . self::export($actual));
        }
    }

    protected function assertTrue(bool $condition, string $message = 'expected true'): void
    {
        $this->assertions++;

        if (!$condition) {
            throw new AssertionFailed($message);
        }
    }

    protected function assertFalse(bool $condition, string $message = 'expected false'): void
    {
        $this->assertTrue(!$condition, $message);
    }

    protected function assertNull(mixed $value, string $message = ''): void
    {
        $this->assertSame(null, $value, $message);
    }

    protected function assertNotNull(mixed $value, string $message = 'expected a value, got null'): void
    {
        $this->assertTrue($value !== null, $message);
    }

    /** @param array<mixed>|\Countable $haystack */
    protected function assertCount(int $expected, array|\Countable $haystack, string $message = ''): void
    {
        $this->assertSame($expected, count($haystack), $message !== '' ? $message : 'count');
    }

    protected function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertTrue(str_contains($haystack, $needle), $message !== '' ? $message : 'expected ' . self::export($haystack) . ' to contain ' . self::export($needle));
    }

    protected function assertStringNotContainsString(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertTrue(!str_contains($haystack, $needle), $message !== '' ? $message : 'expected output not to contain ' . self::export($needle));
    }

    protected function assertLessThanOrEqual(int|float $max, int|float $actual, string $message = ''): void
    {
        $this->assertTrue($actual <= $max, ($message !== '' ? $message . ': ' : '') . "expected <= {$max}, got {$actual}");
    }

    protected function assertGreaterThanOrEqual(int|float $min, int|float $actual, string $message = ''): void
    {
        $this->assertTrue($actual >= $min, ($message !== '' ? $message . ': ' : '') . "expected >= {$min}, got {$actual}");
    }

    /**
     * @template T of \Throwable
     * @param class-string<T> $class
     * @return T
     */
    protected function assertThrows(string $class, callable $callback): \Throwable
    {
        $this->assertions++;

        try {
            $callback();
        } catch (\Throwable $exception) {
            if ($exception instanceof $class) {
                return $exception;
            }

            throw new AssertionFailed('expected ' . $class . ', got ' . get_class($exception));
        }

        throw new AssertionFailed('expected ' . $class . ' to be thrown');
    }

    protected function skip(string $reason): never
    {
        throw new Skipped($reason);
    }

    private static function export(mixed $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        if ($value instanceof \UnitEnum) {
            return get_class($value) . '::' . $value->name;
        }

        return mb_strimwidth($encoded === false ? var_export($value, true) : $encoded, 0, 300, '…');
    }
}
