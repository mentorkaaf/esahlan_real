<?php

namespace App\Console\Commands;

use App\Services\FcmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendCartAbandonmentNotifications extends Command
{
    protected $signature   = 'cart:notify-abandoned';
    protected $description = 'Send FCM push notifications for abandoned carts';

    private array $moduleLabels = [
        'efood'    => ['name' => 'eFood',    'emoji' => '🍕', 'color' => '#FF6B35'],
        'eshop'    => ['name' => 'eShop',    'emoji' => '🛍️', 'color' => '#7C3AED'],
        'egrocery' => ['name' => 'eGrocery', 'emoji' => '🥦', 'color' => '#16A34A'],
        'elaundry' => ['name' => 'eLaundry', 'emoji' => '👕', 'color' => '#0EA5E9'],
        'eparcel'  => ['name' => 'eParcel',  'emoji' => '📦', 'color' => '#F59E0B'],
        'erent'    => ['name' => 'eRent',    'emoji' => '🏠', 'color' => '#EC4899'],
        'emoving'  => ['name' => 'eMoving',  'emoji' => '🚛', 'color' => '#6366F1'],
    ];

    public function handle(): void
    {
        $now = now();

        // Get all carts grouped by user+module that still have items
        $carts = DB::table('cart_items')
            ->select('user_id', 'module', DB::raw('MIN(added_at) as first_added'))
            ->groupBy('user_id', 'module')
            ->get();

        foreach ($carts as $cart) {
            $firstAdded   = \Carbon\Carbon::parse($cart->first_added);
            $minutesOld   = $firstAdded->diffInMinutes($now);
            $hoursOld     = $firstAdded->diffInHours($now);

            // Get notification timestamps for this user+module
            $notifRow = DB::table('cart_items')
                ->where('user_id', $cart->user_id)
                ->where('module', $cart->module)
                ->select('notified_30min_at', 'notified_2h_at', 'notified_24h_at')
                ->first();

            // Get user FCM token
            $user = DB::table('users')
                ->where('id', $cart->user_id)
                ->select('fcm_token', 'name')
                ->first();

            if (!$user?->fcm_token) continue;

            // Get cart items for message
            $items = DB::table('cart_items')
                ->where('user_id', $cart->user_id)
                ->where('module', $cart->module)
                ->get();

            $itemCount   = $items->sum('quantity');
            $firstName   = $items->first()?->product_name ?? 'your item';
            $totalPrice  = $items->sum(fn($i) => $i->price * $i->quantity);
            $moduleInfo  = $this->moduleLabels[$cart->module] ?? ['name' => ucfirst($cart->module), 'emoji' => '🛒'];

            $notified = false;

            // 30 min reminder
            if ($minutesOld >= 30 && !$notifRow->notified_30min_at) {
                $this->sendNotification($user->fcm_token, $moduleInfo, $firstName, $itemCount, $totalPrice, 'first');
                DB::table('cart_items')
                    ->where('user_id', $cart->user_id)
                    ->where('module', $cart->module)
                    ->update(['notified_30min_at' => $now]);
                $notified = true;
            }

            // 2 hour reminder
            if ($hoursOld >= 2 && !$notifRow->notified_2h_at) {
                $this->sendNotification($user->fcm_token, $moduleInfo, $firstName, $itemCount, $totalPrice, 'second');
                DB::table('cart_items')
                    ->where('user_id', $cart->user_id)
                    ->where('module', $cart->module)
                    ->update(['notified_2h_at' => $now]);
                $notified = true;
            }

            // 24 hour reminder
            if ($hoursOld >= 24 && !$notifRow->notified_24h_at) {
                $this->sendNotification($user->fcm_token, $moduleInfo, $firstName, $itemCount, $totalPrice, 'final');
                DB::table('cart_items')
                    ->where('user_id', $cart->user_id)
                    ->where('module', $cart->module)
                    ->update(['notified_24h_at' => $now]);
                $notified = true;
            }

            // 48 hours → auto-clear (stop spamming)
            if ($hoursOld >= 48) {
                DB::table('cart_items')
                    ->where('user_id', $cart->user_id)
                    ->where('module', $cart->module)
                    ->delete();
                Log::info("[CartAbandonment] Auto-cleared cart for user {$cart->user_id} module {$cart->module}");
            }
        }

        $this->info('Cart abandonment notifications sent.');
    }

    private function sendNotification(string $token, array $module, string $firstItem, int $count, float $total, string $stage): void
    {
        $emoji = $module['emoji'];
        $name  = $module['name'];

        [$title, $body] = match ($stage) {
            'first'  => [
                "{$emoji} {$name} Cart",
                $count > 1
                    ? "Waxaad cart-kaaga ku leedahay \"{$firstItem}\" + " . ($count - 1) . " more. Dalbo hadda!"
                    : "Waxaad cart-kaaga ku leedahay \"{$firstItem}\". Dalbo hadda!",
            ],
            'second' => [
                "{$emoji} {$name} — Hadhow dhamaaneysa!",
                $count > 1
                    ? "\"{$firstItem}\" + " . ($count - 1) . " items cart-kaaga ku sugayaan. \$" . number_format($total, 2) . " oo keliya!"
                    : "\"{$firstItem}\" cart-kaaga ku sugaysaa. Ha daalin!",
            ],
            'final'  => [
                "{$emoji} {$name} — Fursad ugu dambeysa! ⏰",
                "Cart-kaaga weli buuxaa. \"{$firstItem}\"" . ($count > 1 ? " + " . ($count - 1) . " more" : "") . " — dhameystir ama la lumeyso!",
            ],
            default => ["{$emoji} {$name} Cart", "Items cart-kaaga ku sugayaan!"],
        };

        try {
            FcmService::sendToToken($token, $title, $body, [
                'type'   => 'cart_abandonment',
                'module' => $module['name'],
                'action' => 'open_cart',
            ]);
        } catch (\Throwable $e) {
            Log::warning("[CartAbandonment] FCM failed: " . $e->getMessage());
        }
    }
}
