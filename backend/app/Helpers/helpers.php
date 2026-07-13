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
     * Serve a public-storage file with full Range-request support.
     *
     * When the request carries ?s= and ?e= parameters the file is treated as
     * signed (private). The HMAC is validated before serving; an invalid or
     * expired signature yields 403. Files without ?s= are served publicly —
     * backward-compatible with all existing unsigned URLs.
     */
    function proxy_storage_file(string $path): \Symfony\Component\HttpFoundation\Response
    {
        $path     = ltrim(str_replace(['..', "\0"], '', $path), '/');

        // ── Signature validation (only when ?s= is present) ──────────
        $req = request();
        $sig = $req->query('s');
        if ($sig !== null) {
            $expires = (int) $req->query('e', 0);
            if (!\App\Services\MediaSigningService::verify($path, $sig, $expires)) {
                abort(403, 'Media link has expired or is invalid.');
            }
            // Signed URLs must not be cached publicly
            $cacheControl = 'private, max-age=3600';
        }

        $realPath = storage_path('app/public/' . $path);
        if (!is_file($realPath)) {
            abort(404);
        }

        $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
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
        };

        $size  = filesize($realPath);
        $start = 0;
        $end   = $size - 1;

        $rangeHeader = request()->header('Range');
        if ($rangeHeader && preg_match('/bytes=(\d*)-(\d*)/i', $rangeHeader, $m)) {
            $start = $m[1] !== '' ? (int) $m[1] : 0;
            $end   = $m[2] !== '' ? (int) $m[2] : $size - 1;
            $end   = min($end, $size - 1);
            $start = min($start, $end);
        }

        $length     = $end - $start + 1;
        $isPartial  = ($rangeHeader !== null);
        $statusCode = $isPartial ? 206 : 200;

        $headers = [
            'Content-Type'                        => $mime,
            'Content-Length'                      => $length,
            'Accept-Ranges'                       => 'bytes',
            'Cache-Control'                       => $cacheControl ?? 'public, max-age=604800',
            'Access-Control-Allow-Origin'         => '*',
            'Access-Control-Allow-Methods'        => 'GET, OPTIONS',
            'Access-Control-Allow-Headers'        => 'Origin, Accept, Content-Type, Range',
            'Access-Control-Expose-Headers'       => 'Content-Length, Content-Range, Accept-Ranges',
            'Cross-Origin-Resource-Policy'        => 'cross-origin',
        ];
        if ($isPartial) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        $capturedStart = $start;
        $capturedLength = $length;

        return response()->stream(function () use ($realPath, $capturedStart, $capturedLength) {
            $fp = fopen($realPath, 'rb');
            fseek($fp, $capturedStart);
            $remaining = $capturedLength;
            while ($remaining > 0 && !feof($fp)) {
                $chunk = fread($fp, min(65536, $remaining));
                if ($chunk === false) break;
                echo $chunk;
                $remaining -= strlen($chunk);
                if (connection_aborted()) break;
            }
            fclose($fp);
        }, $statusCode, $headers);
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

if (!function_exists('signed_media_url')) {
    /**
     * Generate a time-limited signed media URL for private/expiring content.
     *
     * Use for: story media (TTL = time to story expiry), private post media,
     * paid eLearning content, chat attachments.
     *
     * Public images (banners, vendor logos, public post thumbnails) should
     * continue using cdn_url() — no need to sign freely accessible content.
     *
     * @param  string        $pathOrUrl  Storage-relative path OR full cdn_url
     * @param  int|\DateTime $ttl        Seconds (int) or an absolute expiry DateTime
     */
    function signed_media_url(?string $pathOrUrl, int|\DateTime $ttl = 3600): ?string
    {
        if (!$pathOrUrl) return null;

        // Extract the storage-relative path from a full proxy URL
        $path = $pathOrUrl;
        if (str_contains($pathOrUrl, '/api/v1/media')) {
            parse_str(parse_url($pathOrUrl, PHP_URL_QUERY) ?? '', $q);
            $path = rawurldecode($q['f'] ?? '');
        } elseif (str_starts_with($pathOrUrl, 'http')) {
            // External URL — cannot sign, return as-is
            return $pathOrUrl;
        }

        $path      = ltrim($path, '/');
        $ttlSecs   = $ttl instanceof \DateTime ? max(1, $ttl->getTimestamp() - time()) : $ttl;

        return \App\Services\MediaSigningService::url($path, $ttlSecs);
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
