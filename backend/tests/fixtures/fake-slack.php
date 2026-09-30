<?php

declare(strict_types=1);

// Stand-in for slack.com used by the HTTP tests (php -S router). Records every
// request as a JSON line in FAKE_SLACK_LOG and answers like the Web API.

$body = (string) file_get_contents('php://input');
$path = (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
$payload = json_decode($body, true);

file_put_contents((string) getenv('FAKE_SLACK_LOG'), json_encode([
    'path' => $path,
    'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
    'payload' => $payload,
    'at' => microtime(true),
]) . "\n", FILE_APPEND | LOCK_EX);

header('Content-Type: application/json');

if (str_ends_with($path, '/chat.postMessage')) {
    echo json_encode(['ok' => true, 'channel' => $payload['channel'] ?? '', 'ts' => sprintf('%d.%06d', time(), random_int(0, 999999))]);
} elseif (str_ends_with($path, '/chat.update')) {
    usleep(random_int(0, 50_000));
    echo json_encode(['ok' => true, 'channel' => $payload['channel'] ?? '', 'ts' => $payload['ts'] ?? '']);
} elseif (str_starts_with($path, '/api/response/')) {
    header('Content-Type: text/plain');
    echo 'ok';
} else {
    echo json_encode(['ok' => false, 'error' => 'unknown_method']);
}
