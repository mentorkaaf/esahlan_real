<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Services\FcmService;

class SendReviewReminders extends Command
{
    protected $signature = 'global:review-reminders';
    protected $description = 'Send review reminder notifications for delivered orders';

    public function handle()
    {
        // Orders delivered 3+ days ago, max 2 reminders, not yet reviewed
        $orders = DB::table('global_orders as o')
            ->join('global_users as u', 'u.id', '=', 'o.global_user_id')
            ->where('o.status', 'delivered')
            ->where('o.review_reminder_count', '<', 2)
            ->where(function ($q) {
                $q->whereNull('o.review_notified_at')
                  ->orWhere('o.review_notified_at', '<', now()->subDays(3));
            })
            ->where('o.delivered_at', '<', now()->subDays(3))
            ->whereNotExists(function ($q) {
                // No review exists for any item in this order
                $q->select(DB::raw(1))
                  ->from('global_reviews as r')
                  ->join('global_order_items as oi', 'oi.global_product_id', '=', 'r.global_product_id')
                  ->whereColumn('oi.global_order_id', 'o.id')
                  ->whereColumn('r.global_user_id', 'o.global_user_id');
            })
            ->select('o.id', 'o.order_number', 'o.global_user_id', 'o.review_reminder_count',
                     'u.name', 'u.email', 'u.fcm_token')
            ->limit(100)
            ->get();

        foreach ($orders as $order) {
            $isFirst = $order->review_reminder_count === 0;

            // Get first product name
            $item = DB::table('global_order_items')->where('global_order_id', $order->id)->first();
            $productName = $item?->product_name ?? 'your recent purchase';

            // FCM push
            if ($order->fcm_token) {
                FcmService::sendToToken(
                    $order->fcm_token,
                    $isFirst ? '⭐ How was your order?' : '📝 Your review matters!',
                    "Tell us what you think of $productName from order {$order->order_number}",
                    ['type' => 'review_request', 'order_id' => (string) $order->id, 'deep_link' => '/global/orders']
                );
            }

            // Email
            try {
                Mail::to($order->email)->queue(new \App\Mail\Global\GlobalReviewRequestMail([
                    'name'         => $order->name,
                    'order_number' => $order->order_number,
                    'product_name' => $productName,
                    'order_id'     => $order->id,
                    'is_reminder'  => !$isFirst,
                ]));
            } catch (\Throwable $e) {
                Log::error('Review reminder email failed: ' . $e->getMessage());
            }

            DB::table('global_orders')->where('id', $order->id)->update([
                'review_notified_at'    => now(),
                'review_reminder_count' => $order->review_reminder_count + 1,
            ]);
        }

        $this->info('Sent review reminders to ' . $orders->count() . ' orders.');
    }
}
