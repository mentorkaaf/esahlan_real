<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add cached points balance to users (avoids SUM every request)
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('points_balance')->default(0)->after('referral_code');
        });

        // Add points_discount to orders (how many $ were discounted via points)
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('points_discount', 10, 2)->default(0)->after('wallet_used');
            $table->unsignedInteger('points_used')->default(0)->after('points_discount');
            $table->unsignedInteger('points_earned')->default(0)->after('points_used');
        });

        // Reward settings (seeded defaults)
        $settings = [
            ['key' => 'reward_enabled',         'value' => '1',    'type' => 'boolean'],
            ['key' => 'points_per_dollar',       'value' => '10',   'type' => 'integer'], // earn 10 pts per $1 spent
            ['key' => 'points_to_dollar',        'value' => '100',  'type' => 'integer'], // 100 pts = $1 discount
            ['key' => 'points_expire_days',      'value' => '365',  'type' => 'integer'],
            ['key' => 'min_order_for_points',    'value' => '2',    'type' => 'integer'],  // min $2 order to earn
            ['key' => 'max_redeem_percent',      'value' => '50',   'type' => 'integer'], // max 50% of order value
            // Per-module multipliers (×10 = integer, so 2x = 20, 1x = 10, 0.5x = 5)
            ['key' => 'pts_mult_efood',          'value' => '10',   'type' => 'integer'],
            ['key' => 'pts_mult_egrocery',       'value' => '10',   'type' => 'integer'],
            ['key' => 'pts_mult_eshop',          'value' => '10',   'type' => 'integer'],
            ['key' => 'pts_mult_eparcel',        'value' => '10',   'type' => 'integer'],
            ['key' => 'pts_mult_emoving',        'value' => '10',   'type' => 'integer'],
            ['key' => 'pts_mult_erent',          'value' => '10',   'type' => 'integer'],
            ['key' => 'pts_mult_eticket',        'value' => '20',   'type' => 'integer'],
            ['key' => 'pts_mult_elearning',      'value' => '20',   'type' => 'integer'],
            ['key' => 'pts_mult_eexchange',      'value' => '5',    'type' => 'integer'],
            ['key' => 'pts_mult_elaundry',       'value' => '10',   'type' => 'integer'],
            ['key' => 'pts_mult_edata',          'value' => '10',   'type' => 'integer'],
            ['key' => 'pts_mult_ehealth',        'value' => '10',   'type' => 'integer'],
        ];

        foreach ($settings as $s) {
            DB::table('settings')->updateOrInsert(['key' => $s['key']], $s);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('points_balance');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['points_discount', 'points_used', 'points_earned']);
        });
    }
};
