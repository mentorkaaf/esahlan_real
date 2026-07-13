<?php

namespace App\Services;

class MediaSigningService
{
    /**
     * Sign a storage-relative path with an expiry timestamp.
     *
     * Returns just the HMAC hex string (20 bytes / 40 hex chars).
     * The caller assembles the full URL.
     *
     * @param  string $path        Storage-relative path (same value as the ?f= param)
     * @param  int    $ttlSeconds  How long the URL is valid (default 3600 = 1h)
     * @return array{sig: string, expires: int}
     */
    public static function sign(string $path, int $ttlSeconds = 3600): array
    {
        $expires = time() + $ttlSeconds;
        $sig     = self::_hmac($path, $expires);
        return ['sig' => $sig, 'expires' => $expires];
    }

    /**
     * Verify a signed URL.
     *
     * @param  string $path     Storage-relative path (the ?f= value, URL-decoded)
     * @param  string $sig      Hex HMAC from the ?s= param
     * @param  int    $expires  Unix timestamp from the ?e= param
     */
    public static function verify(string $path, string $sig, int $expires): bool
    {
        if ($expires < time()) {
            return false; // expired
        }

        // Constant-time comparison prevents timing attacks
        return hash_equals(self::_hmac($path, $expires), $sig);
    }

    /**
     * Build a full signed media proxy URL.
     *
     * @param  string $path        Storage-relative path (no leading slash)
     * @param  int    $ttlSeconds
     */
    public static function url(string $path, int $ttlSeconds = 3600): string
    {
        $path    = ltrim($path, '/');
        $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));
        ['sig' => $sig, 'expires' => $exp] = self::sign($path, $ttlSeconds);

        return url('/api/v1/media') . '?f=' . $encoded . '&s=' . $sig . '&e=' . $exp;
    }

    // ── private ──────────────────────────────────────────────────────────────

    private static function _hmac(string $path, int $expires): string
    {
        // Derive a 32-byte key from APP_KEY (strip "base64:" prefix if present)
        $appKey = config('app.key', '');
        if (str_starts_with($appKey, 'base64:')) {
            $appKey = base64_decode(substr($appKey, 7));
        }
        $signingKey = substr($appKey, 0, 32);

        // Sign: path + ':' + expires
        return hash_hmac('sha256', $path . ':' . $expires, $signingKey);
    }
}
