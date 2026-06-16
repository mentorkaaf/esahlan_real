<?php

if (!function_exists('settings')) {
    /**
     * Get a setting value by key.
     */
    function settings(string $key, mixed $default = null): mixed
    {
        static $cache = [];

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        try {
            $value = \Illuminate\Support\Facades\Cache::remember(
                "setting_{$key}",
                now()->addHours(6),
                fn () => \App\Models\Setting::where('key', $key)->value('value')
            );

            $cache[$key] = $value ?? $default;
            return $cache[$key];
        } catch (\Throwable) {
            return $default;
        }
    }
}

if (!function_exists('format_currency')) {
    /**
     * Format a number as currency.
     */
    function format_currency(float $amount, string $currency = 'USD'): string
    {
        $symbol = match ($currency) {
            'USD'   => '$',
            'SOS'   => 'SOS',
            'EUR'   => '€',
            'GBP'   => '£',
            default => $currency,
        };
        return $symbol . number_format($amount, 2);
    }
}

if (!function_exists('upload_file')) {
    /**
     * Upload a file to storage and return the path.
     */
    function upload_file(\Illuminate\Http\UploadedFile $file, string $folder = 'uploads'): string
    {
        return $file->store($folder, 'public');
    }
}

if (!function_exists('asset_url')) {
    /**
     * Get the public URL for a stored file.
     */
    function asset_url(?string $path): ?string
    {
        if (!$path) return null;

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
    }
}

if (!function_exists('proxy_storage_file')) {
    /**
     * Stream a public-storage file through PHP with CORS headers.
     *
     * IMPORTANT: we deliberately stream the bytes via PHP instead of using
     * response()->file(). On LiteSpeed/Hostinger, response()->file() triggers an
     * internal sendfile that serves the file through the static handler and BYPASSES
     * both PHP-set headers and .htaccess mod_headers — so the CORS header never
     * reaches the browser on 200 responses (Flutter Web then blocks the image).
     * Streaming keeps the response fully in PHP, so the headers are always sent.
     *
     * Supports HTTP Range requests so videos (trailers/lessons) still seek/stream.
     */
    function proxy_storage_file(string $path): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $realPath = storage_path('app/public/' . $path);
        if (!is_file($realPath)) {
            abort(404);
        }

        $ext  = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'svg'         => 'image/svg+xml',
            'mp4'         => 'video/mp4',
            'webm'        => 'video/webm',
            'mov'         => 'video/quicktime',
            'ogg'         => 'video/ogg',
            'mp3'         => 'audio/mpeg',
            'pdf'         => 'application/pdf',
            default       => 'application/octet-stream',
        };

        $size    = filesize($realPath);
        $headers = [
            'Content-Type'                  => $mime,
            'Access-Control-Allow-Origin'   => '*',
            'Access-Control-Allow-Methods'  => 'GET, OPTIONS',
            'Access-Control-Allow-Headers'  => 'Origin, Accept, Content-Type, Range',
            'Access-Control-Expose-Headers' => 'Content-Length, Content-Range, Accept-Ranges',
            'Cross-Origin-Resource-Policy'  => 'cross-origin',
            'Accept-Ranges'                 => 'bytes',
            // MUST stay uncacheable: the Hostinger CDN (hcdn) caches image-extension
            // URLs as static assets and strips the per-origin CORS headers in the
            // process. Keeping it private/no-store makes the CDN treat it as DYNAMIC
            // (pass-through), so the CORS headers from HandleCors reach the browser.
            'Cache-Control'                 => 'no-store, no-cache, must-revalidate, private',
            'Pragma'                        => 'no-cache',
        ];

        $start  = 0;
        $end    = $size - 1;
        $status = 200;
        $range  = request()->header('Range');
        if ($range && preg_match('/bytes=(\d*)-(\d*)/', $range, $m)) {
            if ($m[1] !== '') $start = (int) $m[1];
            if ($m[2] !== '') $end   = (int) $m[2];
            if ($start > $end || $end >= $size) $end = $size - 1;
            if ($start < 0) $start = 0;
            $status = 206;
            $headers['Content-Range'] = "bytes $start-$end/$size";
        }

        $length = $end - $start + 1;
        $headers['Content-Length'] = $length;

        return response()->stream(function () use ($realPath, $start, $length) {
            $fp = fopen($realPath, 'rb');
            if ($fp === false) return;
            if ($start > 0) fseek($fp, $start);
            $remaining = $length;
            while ($remaining > 0 && !feof($fp)) {
                $chunk = fread($fp, (int) min(8192, $remaining));
                if ($chunk === false) break;
                echo $chunk;
                $remaining -= strlen($chunk);
                flush();
            }
            fclose($fp);
        }, $status, $headers);
    }
}

if (!function_exists('media_proxy_url')) {
    /**
     * Build the CORS-safe media-proxy URL for a public-storage path.
     *
     * The path is passed as a query parameter so the URL has NO file extension.
     * This matters: the Hostinger CDN (hcdn) classifies URLs that end in an image
     * extension (.jpg/.png/...) as cacheable static assets and routes them through a
     * caching pipeline that STRIPS the per-origin CORS headers. An extension-less URL
     * like /api/v1/media?f=... is treated as DYNAMIC (pass-through), so the CORS
     * headers from HandleCors survive and Flutter Web can load the image.
     */
    function media_proxy_url(string $path): string
    {
        $path    = ltrim($path, '/');
        $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));
        return url('/api/v1/media') . '?f=' . $encoded;
    }
}

if (!function_exists('cdn_url')) {
    /**
     * Convert a stored image/video reference into a CORS-safe, CDN-DYNAMIC URL.
     *
     * Flutter Web (CanvasKit) requires CORS headers on images/videos. Our own storage
     * files are routed through the /api/v1/media proxy (extension-less so the CDN
     * doesn't cache+strip CORS); external URLs (YouTube, other CDNs) are left as-is.
     *
     * Accepts a relative storage path ("banners/foo.jpg"), a legacy storage URL
     * ("https://esahlan.com/storage/banners/foo.jpg"), or a legacy proxy URL
     * ("https://esahlan.com/api/v1/img/banners/foo.jpg").
     */
    function cdn_url(?string $pathOrUrl): ?string
    {
        if (!$pathOrUrl) return null;

        $val = trim($pathOrUrl);

        if (str_starts_with($val, 'http://') || str_starts_with($val, 'https://')) {
            // Already one of our proxy URLs (old /api/img or /api/v1/img form).
            if (preg_match('#/api/(?:v1/)?img/(.+)$#', $val, $m)) {
                return media_proxy_url($m[1]);
            }
            // Our own /storage/ file → route through the proxy.
            if (preg_match('#/storage/(.+)$#', $val, $m)) {
                return media_proxy_url($m[1]);
            }
            // External URL (YouTube, etc.) — just force https.
            return str_replace('http://', 'https://', $val);
        }

        // Relative storage path.
        return media_proxy_url($val);
    }
}

if (!function_exists('generate_otp')) {
    /**
     * Generate a random OTP code.
     */
    function generate_otp(int $length = 6): string
    {
        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('mask_phone')) {
    /**
     * Mask a phone number for display.
     */
    function mask_phone(string $phone): string
    {
        if (strlen($phone) < 6) return $phone;
        return substr($phone, 0, 4) . str_repeat('*', strlen($phone) - 7) . substr($phone, -3);
    }
}
