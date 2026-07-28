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

    /** Loaded once per run from DB */
    private array $templates = [];

    public function handle(): void
    {
        // Load all active templates from DB once
        $rows = DB::table('cart_notification_templates')->where('is_active', true)->get();
        foreach ($rows as $row) {
            $this->templates[$row->module][$row->stage] = ['title' => $row->title, 'body' => $row->body];
        }

        $now = now();

        // Get all carts grouped by user+module that still have items
        $carts = DB::table('abandoned_cart_items')
            ->select('user_id', 'module', DB::raw('MIN(added_at) as first_added'))
            ->groupBy('user_id', 'module')
            ->get();

        foreach ($carts as $cart) {
            $firstAdded   = \Carbon\Carbon::parse($cart->first_added);
            $minutesOld   = $firstAdded->diffInMinutes($now);
            $hoursOld     = $firstAdded->diffInHours($now);

            // Get notification timestamps for this user+module
            $notifRow = DB::table('abandoned_cart_items')
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
            $items = DB::table('abandoned_cart_items')
                ->where('user_id', $cart->user_id)
                ->where('module', $cart->module)
                ->get();

            $itemCount  = $items->sum('quantity');
            $firstName  = $items->first()?->product_name ?? 'your item';
            $totalPrice = $items->sum(fn($i) => $i->price * $i->quantity);

            $notified = false;

            // 30 min reminder
            if ($minutesOld >= 30 && !$notifRow->notified_30min_at) {
                $this->sendNotification($user->fcm_token, $cart->module, $firstName, $itemCount, $totalPrice, '30min');
                DB::table('abandoned_cart_items')
                    ->where('user_id', $cart->user_id)
                    ->where('module', $cart->module)
                    ->update(['notified_30min_at' => $now]);
                $notified = true;
            }

            // 2 hour reminder
            if ($hoursOld >= 2 && !$notifRow->notified_2h_at) {
                $this->sendNotification($user->fcm_token, $cart->module, $firstName, $itemCount, $totalPrice, '2h');
                DB::table('abandoned_cart_items')
                    ->where('user_id', $cart->user_id)
                    ->where('module', $cart->module)
                    ->update(['notified_2h_at' => $now]);
                $notified = true;
            }

            // 24 hour reminder
            if ($hoursOld >= 24 && !$notifRow->notified_24h_at) {
                $this->sendNotification($user->fcm_token, $cart->module, $firstName, $itemCount, $totalPrice, '24h');
                DB::table('abandoned_cart_items')
                    ->where('user_id', $cart->user_id)
                    ->where('module', $cart->module)
                    ->update(['notified_24h_at' => $now]);
                $notified = true;
            }

            // 48 hours → auto-clear (stop spamming)
            if ($hoursOld >= 48) {
                DB::table('abandoned_cart_items')
                    ->where('user_id', $cart->user_id)
                    ->where('module', $cart->module)
                    ->delete();
                Log::info("[CartAbandonment] Auto-cleared cart for user {$cart->user_id} module {$cart->module}");
            }
        }

        $this->info('Cart abandonment notifications sent.');
    }

    private function sendNotification(string $token, string $module, string $firstItem, int $count, float $total, string $stage): void
    {
        $tpl = $this->templates[$module][$stage] ?? null;
        if (!$tpl) return; // No active template = skip

        $extra = $count > 1 ? ' + ' . ($count - 1) . ' more' : '';

        $title = $this->replacePlaceholders($tpl['title'], $firstItem, $extra, $total);
        $body  = $this->replacePlaceholders($tpl['body'],  $firstItem, $extra, $total);

        try {
            FcmService::sendToToken($token, $title, $body, [
                'type'   => 'cart_abandonment',
                'module' => $module,
                'action' => 'open_cart',
            ]);
        } catch (\Throwable $e) {
            Log::warning("[CartAbandonment] FCM failed: " . $e->getMessage());
        }
    }

    private function replacePlaceholders(string $text, string $productName, string $extraItems, float $total): string
    {
        return str_replace(
            ['{{product_name}}', '{{extra_items}}', '{{total}}'],
            [$productName, $extraItems, number_format($total, 2)],
            $text
        );
    }
}
