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

if (!function_exists('resized_image_variant')) {
    /**
     * Return the path to a width-downscaled copy of an image, generating and caching
     * it on first request. Returns null to signal "serve the original" (GD missing,
     * unreadable, or source already small enough).
     *
     * Cached at storage/app/public/_thumbs/{w}/{original-path}.
     */
    function resized_image_variant(string $src, string $relPath, string $ext, int $w): ?string
    {
        // Clamp + bucket the width so we don't generate endless variants and to
        // maximise cache hits (round up to the nearest 100, cap at 1600).
        $w = (int) min(1600, max(80, ceil($w / 100) * 100));

        if (!function_exists('imagecreatetruecolor') || !function_exists('getimagesize')) {
            return null; // GD not available — serve original.
        }

        $info = @getimagesize($src);
        if ($info === false || empty($info[0])) return null;
        [$ow, $oh] = $info;
        if ($ow <= $w) return null; // already small enough — serve original.

        $cachePath = storage_path('app/public/_thumbs/' . $w . '/' . $relPath);
        if (is_file($cachePath) && filemtime($cachePath) >= filemtime($src)) {
            return $cachePath; // fresh cached variant.
        }

        $srcImg = match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($src),
            'png'         => @imagecreatefrompng($src),
            'webp'        => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : null,
            default       => null,
        };
        if (!$srcImg) return null;

        $nh  = (int) max(1, round($oh * ($w / $ow)));
        $dst = imagecreatetruecolor($w, $nh);

        if (in_array($ext, ['png', 'webp'], true)) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $w, $nh, $ow, $oh);

        if (!is_dir(dirname($cachePath))) {
            @mkdir(dirname($cachePath), 0775, true);
        }

        $ok = match ($ext) {
            'png'  => imagepng($dst, $cachePath, 6),
            'webp' => function_exists('imagewebp') ? imagewebp($dst, $cachePath, 82) : imagejpeg($dst, $cachePath, 82),
            default => imagejpeg($dst, $cachePath, 82),
        };

        imagedestroy($srcImg);
        imagedestroy($dst);

        return ($ok && is_file($cachePath)) ? $cachePath : null;
    }
}

if (!function_exists('proxy_storage_file')) {
    /**
     * Serve a public-storage file via Nginx X-Accel-Redirect.
     *
     * PHP validates the path (no traversal, file must exist) and optionally
     * generates a resized image variant. Then it hands off to Nginx via
     * X-Accel-Redirect — Nginx serves the bytes using kernel sendfile with zero
     * PHP memory overhead. CORS and cache headers are set by the /x-storage/
     * internal location in nginx.conf, so they always reach the client.
     *
     * Falls back to PHP streaming only when running outside Nginx (e.g. artisan serve).
     */
    function proxy_storage_file(string $path): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $path     = ltrim(str_replace(['..', "\0"], '', $path), '/');
        $realPath = storage_path('app/public/' . $path);
        if (!is_file($realPath)) {
            abort(404);
        }

        $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));

        // On-the-fly downscale for raster images (?w=).
        $w = (int) request()->query('w', 0);
        if ($w > 0 && in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $variant = resized_image_variant($realPath, $path, $ext, $w);
            if ($variant !== null) {
                // variant path is absolute; make it relative to storage/app/public/
                $publicRoot = storage_path('app/public/');
                $path       = ltrim(str_replace($publicRoot, '', $variant), '/');
            }
        }

        // Nginx X-Accel-Redirect — zero PHP memory for actual file bytes.
        // /x-storage/ is an `internal` location in nginx.conf mapping to storage/app/public/.
        return response('', 200, [
            'X-Accel-Redirect'  => '/x-storage/' . $path,
            'X-Accel-Buffering' => 'yes',
            // Content-Type hint so Nginx picks the right MIME (it also detects from extension).
            'Content-Type'      => match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png'         => 'image/png',
                'gif'         => 'image/gif',
                'webp'        => 'image/webp',
                'svg'         => 'image/svg+xml',
                'mp4'         => 'video/mp4',
                'webm'        => 'video/webm',
                'mp3'         => 'audio/mpeg',
                'pdf'         => 'application/pdf',
                default       => 'application/octet-stream',
            },
        ]);
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
            // Normalize api.esahlan.com → esahlan.com so old records stored
            // with the wrong subdomain resolve correctly.
            $val = str_replace('://api.esahlan.com/', '://esahlan.com/', $val);

            // Already one of our proxy URLs (old /api/img or /api/v1/img form).
            if (preg_match('#/api/(?:v1/)?img/(.+)$#', $val, $m)) {
                return media_proxy_url($m[1]);
            }
            // Our own /storage/ file → route through the proxy.
            if (preg_match('#/storage/(.+)$#', $val, $m)) {
                return media_proxy_url($m[1]);
            }
            // Already a correct proxy URL — return as-is (already https).
            if (str_contains($val, '/api/v1/media')) {
                return $val;
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
