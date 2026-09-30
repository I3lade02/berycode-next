<?php

declare(strict_types=1);

namespace BeryCode\Support\Database;

use BeryCode\Support\Config;
use PDO;
use PDOException;

final class Database
{
    private const MYSQL_DUPLICATE_KEY = 1062;
    private const MYSQL_LOCK_WAIT_TIMEOUT = 1205;
    private const MYSQL_DEADLOCK = 1213;

    public static function connect(Config $config): PDO
    {
        $db = $config->database();
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']);

        $pdo = new PDO($dsn, $db['user'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);

        // All DATETIME columns hold UTC values computed in PHP.
        $pdo->exec("SET time_zone = '+00:00'");

        return $pdo;
    }

    /**
     * Runs $callback in a transaction, retrying on deadlocks and lock wait
     * timeouts (both roll back the whole transaction in InnoDB).
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function transaction(PDO $pdo, callable $callback, int $maxAttempts = 3): mixed
    {
        for ($attempt = 1; ; $attempt++) {
            $pdo->beginTransaction();

            try {
                $result = $callback();
                $pdo->commit();

                return $result;
            } catch (\Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if ($attempt < $maxAttempts && $exception instanceof PDOException && self::isRetryable($exception)) {
                    usleep(random_int(10_000, 50_000) * $attempt);
                    continue;
                }

                throw $exception;
            }
        }
    }

    public static function isDuplicateKey(PDOException $exception, ?string $keyName = null): bool
    {
        if ((int) ($exception->errorInfo[1] ?? 0) !== self::MYSQL_DUPLICATE_KEY) {
            return false;
        }

        return $keyName === null || str_contains((string) ($exception->errorInfo[2] ?? ''), $keyName);
    }

    private static function isRetryable(PDOException $exception): bool
    {
        return in_array((int) ($exception->errorInfo[1] ?? 0), [self::MYSQL_DEADLOCK, self::MYSQL_LOCK_WAIT_TIMEOUT], true);
    }
}
