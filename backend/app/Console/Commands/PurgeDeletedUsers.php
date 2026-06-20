<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeDeletedUsers extends Command
{
    protected $signature = 'users:purge-deleted';
    protected $description = 'Permanently remove soft-deleted users and all their related data';

    public function handle()
    {
        $ids = DB::table('users')->whereNotNull('deleted_at')->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('No soft-deleted users found.');
            return;
        }

        $this->info("Found {$ids->count()} soft-deleted users. Purging...");

        $walletIds = DB::table('wallets')
            ->where('owner_type', 'App\\Models\\User')
            ->whereIn('owner_id', $ids)
            ->pluck('id');

        // Delete in order of dependency
        $counts = [];

        $counts['transactions'] = DB::table('transactions')
            ->whereIn('wallet_id', $walletIds)->delete();

        $counts['withdrawal_requests'] = DB::table('withdrawal_requests')
            ->where('owner_type', 'App\\Models\\User')
            ->whereIn('owner_id', $ids)->delete();

        $counts['wallets'] = DB::table('wallets')
            ->whereIn('id', $walletIds)->delete();

        $counts['tokens'] = DB::table('personal_access_tokens')
            ->where('tokenable_type', 'App\\Models\\User')
            ->whereIn('tokenable_id', $ids)->delete();

        $counts['order_status_history'] = DB::table('order_status_history')
            ->whereIn('order_id', DB::table('orders')->whereIn('user_id', $ids)->pluck('id'))
            ->delete();

        $counts['order_items'] = DB::table('order_items')
            ->whereIn('order_id', DB::table('orders')->whereIn('user_id', $ids)->pluck('id'))
            ->delete();

        $counts['orders'] = DB::table('orders')
            ->whereIn('user_id', $ids)->delete();

        // Payment transactions
        if (DB::getSchemaBuilder()->hasTable('payment_transactions')) {
            $counts['payment_transactions'] = DB::table('payment_transactions')
                ->whereIn('user_id', $ids)->delete();
        }

        // Notifications
        if (DB::getSchemaBuilder()->hasTable('notifications')) {
            $counts['notifications'] = DB::table('notifications')
                ->where('notifiable_type', 'App\\Models\\User')
                ->whereIn('notifiable_id', $ids)->delete();
        }

        // Community posts/comments if they exist
        foreach (['community_comments', 'community_likes', 'community_posts'] as $tbl) {
            if (DB::getSchemaBuilder()->hasTable($tbl)) {
                $counts[$tbl] = DB::table($tbl)->whereIn('user_id', $ids)->delete();
            }
        }

        // OTP codes
        if (DB::getSchemaBuilder()->hasTable('otp_codes')) {
            $counts['otp_codes'] = DB::table('otp_codes')
                ->whereIn('phone', DB::table('users')->whereIn('id', $ids)->pluck('phone'))
                ->delete();
        }

        // Finally delete the users themselves
        $counts['users'] = DB::table('users')->whereIn('id', $ids)->delete();

        foreach ($counts as $table => $count) {
            if ($count > 0) {
                $this->line("  ✓ {$table}: {$count} records deleted");
            }
        }

        $this->info('Purge complete.');
    }
}
