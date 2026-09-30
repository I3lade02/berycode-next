<?php

declare(strict_types=1);

namespace BeryCode\Support\Http;

/**
 * Client IP for rate limiting. REMOTE_ADDR by default; a forwarding header is
 * only trusted when SUPPORT_CLIENT_IP_HEADER names it (set it only if the host's
 * own proxy sets that header, otherwise clients could spoof it).
 */
final class ClientIp
{
    public static function resolve(HttpRequest $request, ?string $trustedHeader): string
    {
        if ($trustedHeader !== null && $trustedHeader !== '') {
            $value = $request->header($trustedHeader);

            if ($value !== null) {
                // For X-Forwarded-For style lists the entry appended by the trusted
                // proxy (the right-most one) is the one that cannot be forged.
                $parts = array_reverse(array_map('trim', explode(',', $value)));

                foreach ($parts as $candidate) {
                    if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                        return $candidate;
                    }
                }
            }
        }

        $remote = $request->server['REMOTE_ADDR'] ?? '';

        return is_string($remote) && filter_var($remote, FILTER_VALIDATE_IP) !== false ? $remote : 'unknown';
    }
}
