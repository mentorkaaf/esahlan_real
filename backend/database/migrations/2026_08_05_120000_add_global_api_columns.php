<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add missing columns to global_users
        Schema::table('global_users', function (Blueprint $table) {
            if (!Schema::hasColumn('global_users', 'country'))
                $table->string('country', 2)->nullable()->after('avatar');
            if (!Schema::hasColumn('global_users', 'fcm_token'))
                $table->string('fcm_token')->nullable()->after('country');
            if (!Schema::hasColumn('global_users', 'is_banned'))
                $table->boolean('is_banned')->default(false)->after('is_active');
            if (!Schema::hasColumn('global_users', 'banned_at'))
                $table->timestamp('banned_at')->nullable()->after('is_banned');
            if (!Schema::hasColumn('global_users', 'ban_reason'))
                $table->string('ban_reason')->nullable()->after('banned_at');
        });

        // Add name column to global_addresses for convenience
        Schema::table('global_addresses', function (Blueprint $table) {
            if (!Schema::hasColumn('global_addresses', 'name'))
                $table->string('name')->nullable()->after('global_user_id');
            if (!Schema::hasColumn('global_addresses', 'zip'))
                $table->string('zip')->nullable()->after('zip_code');
            if (!Schema::hasColumn('global_addresses', 'country'))
                $table->string('country', 2)->nullable()->after('country_code');
        });

        // Add shipping_ aliases to global_orders
        Schema::table('global_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('global_orders', 'shipping_name'))
                $table->string('shipping_name')->nullable()->after('global_user_id');
            if (!Schema::hasColumn('global_orders', 'shipping_address1'))
                $table->string('shipping_address1')->nullable();
            if (!Schema::hasColumn('global_orders', 'shipping_city'))
                $table->string('shipping_city')->nullable();
            if (!Schema::hasColumn('global_orders', 'shipping_zip'))
                $table->string('shipping_zip')->nullable();
            if (!Schema::hasColumn('global_orders', 'shipping_country'))
                $table->string('shipping_country', 2)->nullable();
            if (!Schema::hasColumn('global_orders', 'notes'))
                $table->text('notes')->nullable();
            if (!Schema::hasColumn('global_orders', 'paid_at'))
                $table->timestamp('paid_at')->nullable();
            if (!Schema::hasColumn('global_orders', 'shipped_at'))
                $table->timestamp('shipped_at')->nullable();
            if (!Schema::hasColumn('global_orders', 'tracking_number'))
                $table->string('tracking_number')->nullable();
            if (!Schema::hasColumn('global_orders', 'tracking_url'))
                $table->string('tracking_url')->nullable();
            if (!Schema::hasColumn('global_orders', 'shipping_carrier'))
                $table->string('shipping_carrier')->nullable();
        });

        // Cart items table
        if (!Schema::hasTable('global_cart_items')) {
            Schema::create('global_cart_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('global_user_id');
                $table->unsignedBigInteger('global_product_id');
                $table->unsignedInteger('quantity')->default(1);
                $table->string('variant')->nullable();
                $table->decimal('price_snapshot', 10, 2);
                $table->timestamps();

                $table->foreign('global_user_id')->references('id')->on('global_users')->cascadeOnDelete();
                $table->foreign('global_product_id')->references('id')->on('global_products')->cascadeOnDelete();
            });
        }

        // GlobalPayment paid_at column
        Schema::table('global_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('global_payments', 'paid_at'))
                $table->timestamp('paid_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_cart_items');
    }
};
