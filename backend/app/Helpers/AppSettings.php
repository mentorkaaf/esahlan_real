<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AppSettings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = "setting_{$key}";
        return Cache::remember($cacheKey, 600, function () use ($key, $default) {
            $row = DB::table('settings')->where('key', $key)->first();
            if (!$row) return $default;
            return match ($row->type) {
                'boolean' => in_array($row->value, ['1', 'true', true], true),
                'integer' => (int) $row->value,
                'decimal' => (float) $row->value,
                'json'    => json_decode($row->value, true),
                default   => $row->value,
            };
        });
    }

    public static function set(string $key, mixed $value, string $type = 'string'): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'type' => $type, 'updated_at' => now()]
        );
        Cache::forget("setting_{$key}");
    }

    /** Returns the configured app timezone string (e.g. 'Africa/Nairobi') */
    public static function timezone(): string
    {
        return self::get('timezone', config('app.timezone', 'UTC'));
    }

    /** Current time in app timezone as Carbon instance */
    public static function now(): \Carbon\Carbon
    {
        return \Carbon\Carbon::now(self::timezone());
    }
}
