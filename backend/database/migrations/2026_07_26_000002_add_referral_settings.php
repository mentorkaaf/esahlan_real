<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // Add rewarded_at to referrals if missing
        if (!Schema::hasColumn('referrals', 'rewarded_at')) {
            Schema::table('referrals', function (Blueprint $table) {
                $table->timestamp('rewarded_at')->nullable()->after('status');
            });
        }

        // Seed referral config settings
        $settings = [
            ['key' => 'referral_enabled',        'value' => '1',   'type' => 'boolean'],
            ['key' => 'referral_reward_pts',      'value' => '500', 'type' => 'integer'], // pts to referrer on first order
            ['key' => 'referral_commission_pct',  'value' => '5',   'type' => 'integer'], // 5% of order as pts commission
            ['key' => 'referral_l2_pct',          'value' => '2',   'type' => 'integer'], // 2% level-2 commission
            ['key' => 'referral_min_order',       'value' => '5',   'type' => 'integer'], // min $5 order to trigger reward
        ];

        foreach ($settings as $s) {
            DB::table('settings')->updateOrInsert(['key' => $s['key']], $s);
        }
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn('rewarded_at');
        });
    }
};
