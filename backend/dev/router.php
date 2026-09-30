<?php

declare(strict_types=1);

/*
 * Router for PHP's built-in server during local development and tests. Never
 * deployed. Loads .env.local, serves static files, runs the PHP entrypoints and
 * mimics the production deny rule for the private _app directory.
 *
 *   php -S 127.0.0.1:8080 -t backend/web backend/dev/router.php   (npm run support:dev)
 *   php -S 127.0.0.1:8090 -t out backend/dev/router.php           (static export + API)
 */

require dirname(__DIR__) . '/web/api/support/_app/bootstrap.php';

$repoRoot = dirname(__DIR__, 2);

if (getenv('SUPPORT_SKIP_DOTENV') === false) {
    \BeryCode\Support\Cli\DotEnv::load($repoRoot . '/.env.local');
    \BeryCode\Support\Cli\DotEnv::load($repoRoot . '/.env');
}

$documentRoot = realpath((string) $_SERVER['DOCUMENT_ROOT']);
$path = rawurldecode((string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH));

if (str_contains($path, '/_app/') || str_ends_with($path, '/_app')) {
    http_response_code(404);

    return true;
}

$file = $documentRoot === false ? false : realpath($documentRoot . $path);

if ($file !== false && str_starts_with($file, $documentRoot . DIRECTORY_SEPARATOR) && is_file($file) && str_ends_with($file, '.php')) {
    $_SERVER['SCRIPT_FILENAME'] = $file;
    $_SERVER['SCRIPT_NAME'] = $path;
    $_SERVER['PHP_SELF'] = $path;
    chdir(dirname($file));
    require $file;

    return true;
}

// Let the built-in server serve static files and directory index.html files.
return false;
