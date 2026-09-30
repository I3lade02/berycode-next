<?php

declare(strict_types=1);

// Support backend operator CLI. Run `npm run support -- help`.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/web/api/support/_app/bootstrap.php';

exit(\BeryCode\Support\Cli\Cli::main($argv, dirname(__DIR__, 2)));
