<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class EWSetting extends Model
{
    protected $table = 'ewholesale_settings';

    protected $fillable = ['key', 'value', 'type', 'label'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("ew_setting_{$key}", 300, function () use ($key, $default) {
            $row = static::where('key', $key)->first();
            return $row ? $row->typed() : $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : $value]
        );
        Cache::forget("ew_setting_{$key}");
    }

    public function typed(): mixed
    {
        return match($this->type) {
            'json'    => json_decode($this->value, true),
            'boolean' => (bool) $this->value,
            'integer' => (int) $this->value,
            'decimal' => (float) $this->value,
            default   => $this->value,
        };
    }

    public static function defaults(): array
    {
        return [
            ['key' => 'platform_fee_percent',   'value' => '2.5',    'type' => 'decimal',  'label' => 'Platform fee (%)'],
            ['key' => 'default_payment_term',    'value' => 'prepaid','type' => 'string',   'label' => 'Default payment term'],
            ['key' => 'rfq_expiry_days',         'value' => '14',     'type' => 'integer',  'label' => 'RFQ expiry (days)'],
            ['key' => 'min_order_amount',        'value' => '50',     'type' => 'decimal',  'label' => 'Min order amount ($)'],
            ['key' => 'free_shipping_threshold', 'value' => '500',    'type' => 'decimal',  'label' => 'Free shipping over ($)'],
            ['key' => 'base_shipping_fee',       'value' => '3.00',   'type' => 'decimal',  'label' => 'Base shipping fee ($)'],
            ['key' => 'allow_partial_payment',   'value' => '1',      'type' => 'boolean',  'label' => 'Allow partial payment (deposit)'],
            ['key' => 'min_deposit_percent',     'value' => '30',     'type' => 'decimal',  'label' => 'Minimum deposit (%)'],
        ];
    }
}
