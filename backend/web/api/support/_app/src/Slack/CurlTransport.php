<?php

declare(strict_types=1);

namespace BeryCode\Support\Slack;

final class CurlTransport implements SlackTransport
{
    public function post(string $url, array $headers, string $body, int $timeoutSeconds): TransportResult
    {
        if (!function_exists('curl_init')) {
            return TransportResult::networkError('network:curl_unavailable', false);
        }

        $responseHeaders = [];
        $handle = curl_init($url);
        $headerLines = [];

        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(4, $timeoutSeconds),
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);

                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
        ]);

        // Only allow HTTPS in production; plain HTTP is accepted for the local fake Slack used in tests.
        if (defined('CURLOPT_PROTOCOLS')) {
            curl_setopt($handle, CURLOPT_PROTOCOLS, str_starts_with($url, 'https://') ? CURLPROTO_HTTPS : CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }

        $responseBody = curl_exec($handle);

        if ($responseBody === false) {
            $errno = curl_errno($handle);
            $pretransfer = (float) curl_getinfo($handle, CURLINFO_PRETRANSFER_TIME);

            return TransportResult::networkError(self::errorCode($errno), $pretransfer > 0.0);
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        return new TransportResult($status, (string) $responseBody, $responseHeaders);
    }

    private static function errorCode(int $errno): string
    {
        return match ($errno) {
            CURLE_COULDNT_RESOLVE_HOST => 'network:dns',
            CURLE_COULDNT_CONNECT => 'network:connect_failed',
            CURLE_OPERATION_TIMEDOUT => 'network:timeout',
            CURLE_SSL_CONNECT_ERROR, CURLE_SSL_PEER_CERTIFICATE => 'network:tls',
            default => 'network:curl_' . $errno,
        };
    }
}
