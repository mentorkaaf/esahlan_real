<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Order streaks
        if (!Schema::hasTable('user_streaks')) {
            Schema::create('user_streaks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
                $table->unsignedInteger('current_streak')->default(0);
                $table->unsignedInteger('longest_streak')->default(0);
                $table->date('last_order_date')->nullable();
                $table->timestamps();
            });
        }

        // Badge definitions
        if (!Schema::hasTable('badges')) {
            Schema::create('badges', function (Blueprint $table) {
                $table->id();
                $table->string('key', 50)->unique();
                $table->string('name', 100);
                $table->string('description', 255);
                $table->string('icon', 10);   // emoji
                $table->string('category', 30)->default('general'); // orders, spending, tier, streak, social, explorer
                $table->unsignedInteger('pts_reward')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // User earned badges
        if (!Schema::hasTable('user_badges')) {
            Schema::create('user_badges', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('badge_id')->constrained()->onDelete('cascade');
                $table->timestamp('earned_at');
                $table->unique(['user_id', 'badge_id']);
                $table->timestamps();
            });
        }

        // Seed badge definitions
        $badges = [
            // Orders
            ['key' => 'first_order',    'name' => 'First Order',      'description' => 'Placed your very first order',            'icon' => '🎉', 'category' => 'orders',   'pts_reward' => 50],
            ['key' => 'orders_5',       'name' => 'Getting Started',  'description' => 'Completed 5 orders',                      'icon' => '🛍️', 'category' => 'orders',   'pts_reward' => 100],
            ['key' => 'orders_25',      'name' => 'Regular',          'description' => 'Completed 25 orders',                     'icon' => '⭐', 'category' => 'orders',   'pts_reward' => 250],
            ['key' => 'orders_100',     'name' => 'Power User',       'description' => 'Completed 100 orders',                    'icon' => '💪', 'category' => 'orders',   'pts_reward' => 1000],
            // Spending
            ['key' => 'spend_50',       'name' => 'Spender',          'description' => 'Spent $50 total on eSahlan',              'icon' => '💵', 'category' => 'spending',  'pts_reward' => 100],
            ['key' => 'spend_250',      'name' => 'Big Spender',      'description' => 'Spent $250 total on eSahlan',             'icon' => '💸', 'category' => 'spending',  'pts_reward' => 300],
            ['key' => 'spend_1000',     'name' => 'VIP Shopper',      'description' => 'Spent $1,000 total on eSahlan',           'icon' => '🏆', 'category' => 'spending',  'pts_reward' => 1000],
            // Streaks
            ['key' => 'streak_7',       'name' => '7-Day Streak',     'description' => 'Ordered every day for 7 days straight',   'icon' => '🔥', 'category' => 'streak',   'pts_reward' => 200],
            ['key' => 'streak_30',      'name' => '30-Day Streak',    'description' => 'Ordered every day for 30 days straight',  'icon' => '🌟', 'category' => 'streak',   'pts_reward' => 1000],
            ['key' => 'streak_100',     'name' => 'On Fire',          'description' => 'Ordered every day for 100 days straight', 'icon' => '🚀', 'category' => 'streak',   'pts_reward' => 5000],
            // Social
            ['key' => 'first_referral', 'name' => 'Connector',        'description' => 'Referred your first friend',              'icon' => '🤝', 'category' => 'social',   'pts_reward' => 100],
            ['key' => 'referrals_5',    'name' => 'Community Builder', 'description' => 'Referred 5 friends',                     'icon' => '👥', 'category' => 'social',   'pts_reward' => 500],
            // Tiers
            ['key' => 'tier_silver',    'name' => 'Silver Member',    'description' => 'Reached Silver tier',                     'icon' => '🥈', 'category' => 'tier',     'pts_reward' => 0],
            ['key' => 'tier_gold',      'name' => 'Gold Member',      'description' => 'Reached Gold tier',                       'icon' => '🥇', 'category' => 'tier',     'pts_reward' => 0],
            ['key' => 'tier_platinum',  'name' => 'Platinum Member',  'description' => 'Reached Platinum tier — the highest!',    'icon' => '💎', 'category' => 'tier',     'pts_reward' => 0],
            // Explorer
            ['key' => 'explorer',       'name' => 'Explorer',         'description' => 'Used at least 5 different eSahlan modules','icon' => '🗺️', 'category' => 'explorer', 'pts_reward' => 300],
        ];

        foreach ($badges as $b) {
            DB::table('badges')->updateOrInsert(['key' => $b['key']], array_merge($b, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // Gamification settings
        $settings = [
            ['key' => 'streak_enabled',  'value' => '1',  'type' => 'boolean'],
            ['key' => 'badges_enabled',  'value' => '1',  'type' => 'boolean'],
            ['key' => 'leaderboard_enabled', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'streak_grace_hours',  'value' => '48', 'type' => 'integer'], // hours before streak breaks
        ];
        foreach ($settings as $s) {
            DB::table('settings')->updateOrInsert(['key' => $s['key']], $s);
        }

        // Add streak_bonus_pts column to orders if missing
        if (!Schema::hasColumn('orders', 'streak_bonus_pts')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedInteger('streak_bonus_pts')->default(0)->after('points_earned');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('user_streaks');
    }
};
