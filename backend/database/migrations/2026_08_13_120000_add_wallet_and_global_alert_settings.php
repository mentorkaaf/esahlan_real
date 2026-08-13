<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now  = now();
        $rows = [
            // ── ePay Wallet ───────────────────────────────────────────────────────
            [
                'key'         => 'wallet_topup',
                'label'       => 'Wallet Top-up Completed',
                'category'    => 'wallet',
                'is_enabled'  => true,
                'description' => 'Email when a customer successfully tops up their ePay wallet (via Waafi Pay or admin-approved Mobile Pay).',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'wallet_topup_request',
                'label'       => 'Mobile Pay Top-up Request',
                'category'    => 'wallet',
                'is_enabled'  => true,
                'description' => 'Email when a customer submits a Mobile Pay top-up proof and is waiting for admin approval.',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'withdrawal_request',
                'label'       => 'Wallet Withdrawal Request',
                'category'    => 'wallet',
                'is_enabled'  => true,
                'description' => 'Email when a customer submits a withdrawal request from their ePay wallet.',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'large_transaction',
                'label'       => 'Large Wallet Transaction (>$200)',
                'category'    => 'wallet',
                'is_enabled'  => true,
                'description' => 'Email when a single wallet top-up or transfer exceeds $200 (fraud detection).',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            // ── Global Store ─────────────────────────────────────────────────────
            [
                'key'         => 'new_global_order',
                'label'       => 'New Global Store Order',
                'category'    => 'global_store',
                'is_enabled'  => true,
                'description' => 'Email when a customer completes checkout on the Global Store (Stripe or PayPal payment confirmed).',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'global_order_refunded',
                'label'       => 'Global Store Refund Issued',
                'category'    => 'global_store',
                'is_enabled'  => true,
                'description' => 'Email when admin processes a refund on a Global Store order via Stripe or PayPal.',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ];

        // Insert only rows that don't already exist (idempotent)
        foreach ($rows as $row) {
            if (!DB::table('admin_alert_settings')->where('key', $row['key'])->exists()) {
                DB::table('admin_alert_settings')->insert($row);
            }
        }
    }

    public function down(): void
    {
        DB::table('admin_alert_settings')->whereIn('key', [
            'wallet_topup', 'wallet_topup_request', 'withdrawal_request',
            'large_transaction', 'new_global_order', 'global_order_refunded',
        ])->delete();
    }
};
