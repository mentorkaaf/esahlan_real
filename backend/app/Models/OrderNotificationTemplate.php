<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class OrderNotificationTemplate extends Model
{
    protected $fillable = ['module_slug', 'target', 'status', 'title', 'body'];

    public static function resolve(string $status, ?string $moduleSlug, string $target = 'customer'): array
    {
        $cacheKey = "notif_tpl_{$target}_{$moduleSlug}_{$status}";

        return Cache::remember($cacheKey, 300, function () use ($status, $moduleSlug, $target) {
            if ($moduleSlug) {
                $tpl = static::where('module_slug', $moduleSlug)->where('status', $status)->where('target', $target)->first();
                if ($tpl) return ['title' => $tpl->title, 'body' => $tpl->body];
            }
            $tpl = static::whereNull('module_slug')->where('status', $status)->where('target', $target)->first();
            if ($tpl) return ['title' => $tpl->title, 'body' => $tpl->body];

            return ['title' => 'Order Update', 'body' => "Order #{order_number} status: {$status}."];
        });
    }

    public static function clearCache(?string $moduleSlug = null): void
    {
        $statuses = ['pending','confirmed','preparing','ready_for_pickup','out_for_delivery','delivered','cancelled','refunded','failed'];
        foreach (['customer', 'driver'] as $target) {
            foreach ($statuses as $s) {
                Cache::forget("notif_tpl_{$target}_{$moduleSlug}_{$s}");
            }
        }
    }
}
