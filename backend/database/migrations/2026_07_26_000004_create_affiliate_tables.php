<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Affiliate accounts (one per user who applied and was approved)
        if (!Schema::hasTable('affiliates')) {
            Schema::create('affiliates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('code', 16)->unique();
                $table->enum('status', ['pending', 'active', 'suspended'])->default('pending');
                $table->unsignedTinyInteger('commission_pct')->nullable(); // override; null = use global
                $table->unsignedInteger('total_clicks')->default(0);
                $table->unsignedInteger('total_conversions')->default(0);
                $table->decimal('total_earned_pts', 12, 2)->default(0); // lifetime pts earned
                $table->decimal('pending_payout_pts', 12, 2)->default(0); // pts awaiting payout
                $table->text('notes')->nullable(); // admin notes
                $table->timestamps();
            });
        }

        // Every order placed by a user who registered via an affiliate link
        if (!Schema::hasTable('affiliate_conversions')) {
            Schema::create('affiliate_conversions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('affiliate_id')->constrained()->onDelete('cascade');
                $table->foreignId('order_id')->constrained()->onDelete('cascade');
                $table->foreignId('user_id')->constrained()->onDelete('cascade'); // the buyer
                $table->decimal('order_amount', 10, 2);
                $table->unsignedInteger('commission_pts');
                $table->enum('status', ['pending', 'credited', 'reversed'])->default('pending');
                $table->timestamp('credited_at')->nullable();
                $table->timestamps();
            });
        }

        // Payout requests (affiliate requests withdrawal of earned pts → wallet credit)
        if (!Schema::hasTable('affiliate_payouts')) {
            Schema::create('affiliate_payouts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('affiliate_id')->constrained()->onDelete('cascade');
                $table->unsignedInteger('points_requested');
                $table->decimal('dollar_value', 10, 2); // pts converted to $ at current rate
                $table->enum('status', ['pending', 'approved', 'paid', 'rejected'])->default('pending');
                $table->text('admin_note')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }

        // Track which affiliate brought which user (stored on user at registration)
        if (!Schema::hasColumn('users', 'affiliate_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('affiliate_id')->nullable()->after('referred_by');
                $table->foreign('affiliate_id')->references('id')->on('affiliates')->nullOnDelete();
            });
        }

        // Affiliate settings
        $settings = [
            ['key' => 'affiliate_enabled',        'value' => '1',  'type' => 'boolean'],
            ['key' => 'affiliate_commission_pct',  'value' => '3',  'type' => 'integer'], // 3% of order as pts
            ['key' => 'affiliate_payout_min_pts',  'value' => '1000', 'type' => 'integer'], // min 1000 pts to request payout
            ['key' => 'affiliate_auto_approve',    'value' => '0',  'type' => 'boolean'], // manual approval by default
            ['key' => 'affiliate_cookie_days',     'value' => '30', 'type' => 'integer'], // attribution window
        ];

        foreach ($settings as $s) {
            DB::table('settings')->updateOrInsert(['key' => $s['key']], $s);
        }
    }

    public function down(): void
    {
        Schema::table('users', fn($t) => $t->dropForeign(['affiliate_id']));
        Schema::table('users', fn($t) => $t->dropColumn('affiliate_id'));
        Schema::dropIfExists('affiliate_payouts');
        Schema::dropIfExists('affiliate_conversions');
        Schema::dropIfExists('affiliates');
    }
};
