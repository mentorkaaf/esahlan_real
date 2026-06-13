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
