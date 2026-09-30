<?php

declare(strict_types=1);

require dirname(__DIR__) . '/web/api/support/_app/bootstrap.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'BeryCode\\Support\\Tests\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
