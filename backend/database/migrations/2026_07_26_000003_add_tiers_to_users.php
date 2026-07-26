<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'total_points_earned')) {
                $table->unsignedInteger('total_points_earned')->default(0)->after('points_balance');
            }
            if (!Schema::hasColumn('users', 'tier')) {
                $table->string('tier', 20)->default('bronze')->after('total_points_earned');
            }
        });

        // Tier threshold settings
        $settings = [
            ['key' => 'tier_silver_pts',   'value' => '1000',  'type' => 'integer'],
            ['key' => 'tier_gold_pts',     'value' => '5000',  'type' => 'integer'],
            ['key' => 'tier_platinum_pts', 'value' => '20000', 'type' => 'integer'],
            // Bonus earn multiplier per tier (added on top of normal rate), ×10 scale
            ['key' => 'tier_bronze_bonus',   'value' => '0',  'type' => 'integer'],   // 0% bonus
            ['key' => 'tier_silver_bonus',   'value' => '10', 'type' => 'integer'],   // +10% bonus
            ['key' => 'tier_gold_bonus',     'value' => '25', 'type' => 'integer'],   // +25% bonus
            ['key' => 'tier_platinum_bonus', 'value' => '50', 'type' => 'integer'],   // +50% bonus
            // Extra max-redeem % per tier (added on top of base max_redeem_percent)
            ['key' => 'tier_bronze_redeem_extra',   'value' => '0',  'type' => 'integer'],
            ['key' => 'tier_silver_redeem_extra',   'value' => '5',  'type' => 'integer'],
            ['key' => 'tier_gold_redeem_extra',     'value' => '10', 'type' => 'integer'],
            ['key' => 'tier_platinum_redeem_extra', 'value' => '20', 'type' => 'integer'],
        ];

        foreach ($settings as $s) {
            DB::table('settings')->updateOrInsert(['key' => $s['key']], $s);
        }

        // Back-fill total_points_earned from loyalty_points ledger
        DB::statement("
            UPDATE users u
            SET u.total_points_earned = COALESCE((
                SELECT SUM(lp.points)
                FROM loyalty_points lp
                WHERE lp.user_id = u.id AND lp.type = 'earned'
            ), 0)
            WHERE u.total_points_earned = 0
        ");

        // Back-fill tier based on total_points_earned
        DB::statement("
            UPDATE users SET tier =
                CASE
                    WHEN total_points_earned >= 20000 THEN 'platinum'
                    WHEN total_points_earned >= 5000  THEN 'gold'
                    WHEN total_points_earned >= 1000  THEN 'silver'
                    ELSE 'bronze'
                END
        ");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['total_points_earned', 'tier']);
        });
    }
};
