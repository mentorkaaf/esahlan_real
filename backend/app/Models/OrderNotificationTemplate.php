<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class OrderNotificationTemplate extends Model
{
    protected $fillable = ['module_slug', 'status', 'title', 'body'];

    /**
     * Get the best title/body for a given status + module.
     * Falls back: module-specific → global default → hardcoded.
     */
    public static function resolve(string $status, ?string $moduleSlug): array
    {
        $cacheKey = "notif_tpl_{$moduleSlug}_{$status}";

        return Cache::remember($cacheKey, 300, function () use ($status, $moduleSlug) {
            // Try module-specific first
            if ($moduleSlug) {
                $tpl = static::where('module_slug', $moduleSlug)->where('status', $status)->first();
                if ($tpl) return ['title' => $tpl->title, 'body' => $tpl->body];
            }
            // Global default
            $tpl = static::whereNull('module_slug')->where('status', $status)->first();
            if ($tpl) return ['title' => $tpl->title, 'body' => $tpl->body];

            // Hardcoded fallback
            return ['title' => 'Order Update', 'body' => "Your order status changed to {$status}."];
        });
    }

    public static function clearCache(?string $moduleSlug = null): void
    {
        $statuses = ['pending','confirmed','preparing','ready_for_pickup','out_for_delivery','delivered','cancelled','refunded','failed'];
        foreach ($statuses as $s) {
            Cache::forget("notif_tpl_{$moduleSlug}_{$s}");
        }
    }
}
