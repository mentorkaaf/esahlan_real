<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add missing fields to global_coupons
        Schema::table('global_coupons', function (Blueprint $table) {
            $table->string('name')->after('id')->default('');
            $table->string('description')->nullable()->after('name');
            $table->string('label')->default('Deal')->after('description'); // "New User", "Flash", etc.
            $table->boolean('is_new_user_only')->default(false)->after('is_active');
            $table->integer('per_user_limit')->default(1)->after('usage_limit');
            $table->timestamp('starts_at')->nullable()->after('expires_at');
        });

        // Create global_user_coupons (collected coupons wallet)
        Schema::create('global_user_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('global_user_id')->constrained('global_users')->cascadeOnDelete();
            $table->foreignId('global_coupon_id')->constrained('global_coupons')->cascadeOnDelete();
            $table->timestamp('collected_at')->useCurrent();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('global_order_id')->nullable()->constrained('global_orders')->nullOnDelete();
            $table->boolean('is_used')->default(false);
            $table->timestamps();

            $table->unique(['global_user_id', 'global_coupon_id']); // 1 collect per coupon per user
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_user_coupons');
        Schema::table('global_coupons', function (Blueprint $table) {
            $table->dropColumn(['name','description','label','is_new_user_only','per_user_limit','starts_at']);
        });
    }
};
