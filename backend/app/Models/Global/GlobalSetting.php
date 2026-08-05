<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class GlobalSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("gs:{$key}", 300, function () use ($key, $default) {
            return static::where('key', $key)->value('value') ?? $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("gs:{$key}");
    }

    public static function setMany(array $data): void
    {
        foreach ($data as $key => $value) {
            static::set($key, $value);
        }
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        return (bool) static::get($key, $default ? '1' : '0');
    }

    public static function stripe(): array
    {
        $test = static::getBool('global_stripe_test_mode', true);
        return [
            'enabled'    => static::getBool('global_stripe_enabled'),
            'test_mode'  => $test,
            'public_key' => $test ? static::get('global_stripe_pk_test') : static::get('global_stripe_pk_live'),
            'secret_key' => $test ? static::get('global_stripe_sk_test') : static::get('global_stripe_sk_live'),
            'webhook'    => static::get('global_stripe_webhook_secret'),
        ];
    }

    public static function paypal(): array
    {
        $test = static::getBool('global_paypal_test_mode', true);
        return [
            'enabled'    => static::getBool('global_paypal_enabled'),
            'test_mode'  => $test,
            'client_id'  => $test ? static::get('global_paypal_client_id_test') : static::get('global_paypal_client_id_live'),
            'secret'     => $test ? static::get('global_paypal_secret_test') : static::get('global_paypal_secret_live'),
        ];
    }
}
