<?php

declare(strict_types=1);

/*
 * Shared bootstrap for the support backend: loaded by the public entrypoints,
 * the CLI and the tests. It contains no secrets and produces no output, so a
 * direct HTTP request to this file is harmless.
 */

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit;
}

if (!defined('BERYCODE_SUPPORT_APP')) {
    define('BERYCODE_SUPPORT_APP', __DIR__);

    spl_autoload_register(static function (string $class): void {
        $prefix = 'BeryCode\\Support\\';

        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

        if (is_file($file)) {
            require $file;
        }
    });
}
