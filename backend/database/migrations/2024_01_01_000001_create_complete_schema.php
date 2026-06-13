<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Single consolidated migration — creates the entire eSahlan schema.
 * MySQL-compatible. No FK ordering issues. No duplicate ENUMs.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // ── 1. ROLES & PERMISSIONS ────────────────────────────────────────
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->string('guard_name', 50)->default('api');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('slug', 191)->unique();
            $table->string('module', 100)->nullable();
            $table->string('group', 100);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        // ── 2. DISTRICTS ──────────────────────────────────────────────────
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_so')->nullable();
            $table->string('name_ar')->nullable();
            $table->string('slug')->unique();
            $table->string('city')->default('Mogadishu');
            $table->string('country')->default('Somalia');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->json('boundary_polygon')->nullable();
            $table->string('image')->nullable();
            $table->string('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('status');
        });

        // ── 3. MODULES ────────────────────────────────────────────────────
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_so')->nullable();
            $table->string('name_ar')->nullable();
            $table->string('slug', 100)->unique();
            $table->string('icon')->nullable();
            $table->string('cover_image')->nullable();
            $table->text('description')->nullable();
            $table->string('color', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_available')->default(true);
            $table->integer('sort_order')->default(0);
            $table->enum('commission_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('commission_value', 10, 2)->default(0);
            $table->decimal('min_order_amount', 10, 2)->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('module_district', function (Blueprint $table) {
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('district_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->primary(['module_id', 'district_id']);
        });

        // ── 4. USERS ──────────────────────────────────────────────────────
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->string('phone', 20)->unique();
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('avatar')->nullable();
            $table->string('referral_code', 20)->unique();
            $table->unsignedBigInteger('referred_by')->nullable();
            $table->enum('status', ['active', 'inactive', 'banned', 'pending'])->default('pending');
            $table->text('fcm_token')->nullable();
            $table->string('preferred_language', 10)->default('so');
            $table->boolean('dark_mode')->default(false);
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('district_id')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'role_id']);
        });

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->string('code', 10);
            $table->enum('type', ['register', 'login', 'reset', 'verify']);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['phone', 'type']);
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_permissions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->boolean('granted')->default(true);
            $table->primary(['user_id', 'permission_id']);
        });

        // ── 5. VENDORS ────────────────────────────────────────────────────
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('module_id')->nullable();
            $table->string('module_slug', 50)->nullable();
            $table->unsignedBigInteger('district_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique()->nullable();
            $table->text('description')->nullable();
            $table->string('logo', 500)->nullable();
            $table->string('cover_image', 500)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('address', 500)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('vendor_type', 100)->nullable();
            $table->string('status', 30)->default('active');
            $table->boolean('is_open')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_approved')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->boolean('temporarily_closed')->default(false);
            $table->decimal('minimum_order', 10, 2)->nullable();
            $table->decimal('delivery_fee', 10, 2)->nullable();
            $table->string('delivery_time', 30)->nullable();
            $table->decimal('tax_percentage', 5, 2)->nullable();
            $table->string('commission_type', 30)->default('inherit');
            $table->decimal('commission_value', 10, 2)->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('review_count')->default(0);
            $table->json('working_hours')->nullable();
            $table->json('meta')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['status', 'module_id', 'district_id']);
        });

        Schema::create('vendor_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('day');
            $table->boolean('is_open')->default(true);
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->unique(['vendor_id', 'day']);
        });

        Schema::create('vendor_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 100)->default('employee');
            $table->json('permissions')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('vendor_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('type', 100);
            $table->string('file_path');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        // ── 6. CATEGORIES & PRODUCTS ──────────────────────────────────────
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('module_id')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->string('name_so')->nullable();
            $table->string('name_ar')->nullable();
            $table->string('slug');
            $table->string('image')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['module_id', 'vendor_id', 'is_active']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('module_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name');
            $table->string('name_so')->nullable();
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('sku', 100)->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->decimal('compare_price', 10, 2)->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->string('thumbnail', 500)->nullable();
            $table->string('image', 500)->nullable();
            $table->boolean('track_inventory')->default(true);
            $table->integer('stock_quantity')->default(0);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('total_reviews')->default(0);
            $table->string('unit', 50)->nullable();
            $table->integer('min_qty')->default(1);
            $table->integer('max_qty')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->string('brand', 100)->nullable();
            $table->text('tags')->nullable();
            $table->decimal('weight', 8, 3)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->time('available_from')->nullable();
            $table->time('available_until')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['vendor_id', 'category_id', 'is_available']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('image');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku', 100)->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('stock_quantity')->default(0);
            $table->json('attributes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->boolean('is_required')->default(false);
            $table->integer('max_select')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_addons', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(false);
            $table->primary(['product_id', 'addon_id']);
        });

        // ── 7. DELIVERYMEN ────────────────────────────────────────────────
        Schema::create('deliverymen', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('district_id')->nullable();
            $table->string('national_id', 100)->nullable();
            $table->enum('vehicle_type', ['motorcycle', 'car', 'van', 'truck', 'bicycle'])->default('motorcycle');
            $table->string('vehicle_plate', 50)->nullable();
            $table->string('vehicle_model', 100)->nullable();
            $table->string('license_number', 100)->nullable();
            $table->enum('status', ['pending', 'available', 'busy', 'offline', 'banned'])->default('pending');
            $table->boolean('is_approved')->default(false);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamp('last_location_at')->nullable();
            $table->decimal('rating', 3, 2)->default(5.00);
            $table->integer('total_deliveries')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'is_approved']);
        });

        Schema::create('deliveryman_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliveryman_id')->constrained()->cascadeOnDelete();
            $table->string('type', 100);
            $table->string('file_path');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('deliveryman_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliveryman_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('order_id')->nullable(); // no FK — orders table created after
            $table->enum('type', ['delivery_fee', 'bonus', 'incentive', 'penalty']);
            $table->decimal('amount', 10, 2);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['deliveryman_id', 'created_at']);
        });

        // ── 8. COUPONS, ORDERS, WALLETS ───────────────────────────────────
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed']);
            $table->decimal('value', 10, 2);
            $table->decimal('min_order_amount', 10, 2)->default(0);
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_per_user')->default(1);
            $table->integer('used_count')->default(0);
            $table->unsignedBigInteger('module_id')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('module_slug', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['code', 'is_active']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('order_number', 50)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('module_id')->nullable();
            $table->string('module_slug', 50)->nullable();
            $table->unsignedBigInteger('deliveryman_id')->nullable();
            $table->enum('status', ['pending','confirmed','preparing','ready_for_pickup','out_for_delivery','delivered','cancelled','refunded','failed'])->default('pending');
            $table->enum('payment_status', ['unpaid','paid','partial','refunded'])->default('unpaid');
            $table->string('payment_method', 100);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('coupon_discount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('wallet_used', 10, 2)->default(0);
            $table->decimal('commission', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->json('delivery_address');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['vendor_id', 'status']);
            $table->index('created_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->integer('quantity');
            $table->json('addons')->nullable();
            $table->decimal('total', 10, 2);
            $table->json('meta')->nullable();
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 100);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_type', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('order_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('deliveryman_id');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 100);
            $table->unsignedBigInteger('owner_id');
            $table->decimal('balance', 12, 2)->default(0.00);
            $table->decimal('pending_balance', 12, 2)->default(0.00);
            $table->decimal('total_earned', 12, 2)->default(0.00);
            $table->decimal('total_withdrawn', 12, 2)->default(0.00);
            $table->string('currency', 10)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['owner_type', 'owner_id']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->text('note')->nullable();
            $table->string('payment_method', 100)->nullable();
            $table->string('payment_reference', 255)->nullable();
            $table->enum('status', ['pending','completed','failed','cancelled'])->default('completed');
            $table->timestamps();
            $table->index(['wallet_id', 'type', 'created_at']);
        });

        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('owner_type', 100)->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('method', 100);
            $table->string('payment_method', 100)->nullable();
            $table->string('account_number', 100)->nullable();
            $table->string('account_name', 100)->nullable();
            $table->json('account_details')->nullable();
            $table->enum('status', ['pending','approved','rejected','processed'])->default('pending');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('note')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('transaction_reference', 200)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('module_id');
            $table->enum('commission_type', ['percentage', 'fixed']);
            $table->decimal('commission_rate', 10, 2);
            $table->decimal('order_amount', 12, 2);
            $table->decimal('commission_amount', 12, 2);
            $table->decimal('vendor_earning', 12, 2);
            $table->enum('status', ['pending', 'settled'])->default('pending');
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['vendor_id', 'status']);
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 255)->unique();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 100)->default('waafi');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('USD');
            $table->enum('status', ['pending','success','failed','refunded'])->default('pending');
            $table->string('gateway_reference', 255)->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamps();
        });

        // ── 9. MARKETING, CHAT, SOCIAL ────────────────────────────────────
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle', 300)->nullable();
            $table->string('image');
            $table->enum('link_type', ['module','vendor','product','url','none'])->default('none');
            $table->string('link_value', 255)->nullable();
            $table->string('action_url', 500)->nullable();
            $table->string('action_type', 50)->nullable();
            $table->unsignedBigInteger('module_id')->nullable();
            $table->string('module_slug', 50)->nullable();
            $table->unsignedBigInteger('district_id')->nullable();
            $table->enum('position', ['home_top','home_middle','module_top','popup'])->default('home_top');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['position', 'is_active']);
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->enum('type', ['banner','popup','notification','flash_sale']);
            $table->enum('target', ['all','district','module','vendor'])->default('all');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();
        });

        Schema::create('loyalty_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('points');
            $table->enum('type', ['earned','redeemed','expired','bonus']);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'type']);
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('reward_amount', 10, 2)->default(0);
            $table->enum('status', ['pending','rewarded','cancelled'])->default('pending');
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['referrer_id','referred_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type','notifiable_id','read_at']);
        });

        Schema::create('push_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->enum('target_type', ['all','role','user','vendor','deliveryman'])->default('all');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->integer('sent_count')->default(0);
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->timestamps();
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('type', ['customer_vendor','customer_support','vendor_deliveryman']);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->timestamps();
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('participant_type', 100);
            $table->unsignedBigInteger('participant_id');
            $table->timestamp('last_read_at')->nullable();
            $table->unique(['conversation_id','participant_type','participant_id'], 'conv_part_unique');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('sender_type', 100);
            $table->unsignedBigInteger('sender_id');
            $table->enum('type', ['text','image','file'])->default('text');
            $table->text('body')->nullable();
            $table->string('file_path', 255)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('reviewable_type', 100);
            $table->unsignedBigInteger('reviewable_id');
            $table->tinyInteger('rating');
            $table->text('comment')->nullable();
            $table->json('images')->nullable();
            $table->boolean('is_approved')->default(true);
            $table->text('vendor_reply')->nullable();
            $table->timestamp('vendor_replied_at')->nullable();
            $table->timestamps();
            $table->index(['reviewable_type','reviewable_id']);
        });

        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100)->default('Home');
            $table->text('address');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->unsignedBigInteger('district_id')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('wishlist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id','product_id']);
        });

        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coupon_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('order_id');
            $table->decimal('discount_amount', 10, 2);
            $table->timestamp('created_at')->useCurrent();
        });

        // ── 10. SETTINGS, AUDIT, CART ─────────────────────────────────────
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 191)->unique();
            $table->longText('value')->nullable();
            $table->enum('type', ['string','boolean','json','integer','decimal'])->default('string');
            $table->string('group', 100)->default('general');
            $table->timestamps();
            $table->index('group');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event', 100);
            $table->string('auditable_type', 191);
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('module_id')->nullable();
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->timestamps();
            $table->unique('user_id');
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->json('addons')->nullable();
            $table->timestamps();
        });

        // ── 11. MODULE-SPECIFIC: eTicket ──────────────────────────────────
        Schema::create('airlines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 10)->nullable();
            $table->string('logo')->nullable();
            $table->string('color', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('flight_routes', function (Blueprint $table) {
            $table->id();
            $table->string('from_city');
            $table->string('from_code', 10)->nullable();
            $table->string('from_country', 100)->nullable();
            $table->string('to_city');
            $table->string('to_code', 10)->nullable();
            $table->string('to_country', 100)->nullable();
            $table->enum('route_type', ['domestic', 'international'])->default('domestic');
            $table->unsignedInteger('distance_km')->nullable();
            $table->unsignedInteger('default_duration')->nullable();
            $table->decimal('economy_price', 10, 2)->default(0);
            $table->decimal('business_price', 10, 2)->default(0);
            $table->decimal('first_price', 10, 2)->default(0);
            $table->integer('duration_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('airline_id')->nullable();
            $table->string('flight_number', 50);
            $table->string('airline');
            $table->string('from_city');
            $table->string('to_city');
            $table->string('from_code', 10)->nullable();
            $table->string('to_code', 10)->nullable();
            $table->date('departure_date')->nullable();
            $table->string('departure_time', 10)->nullable();
            $table->string('arrival_time', 10)->nullable();
            $table->string('duration', 20)->nullable();
            $table->dateTime('departure_at')->nullable();
            $table->dateTime('arrival_at')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->string('aircraft_type', 100)->nullable();
            $table->json('seat_classes');
            $table->integer('available_seats')->default(0);
            $table->integer('total_seats')->default(100);
            $table->boolean('is_active')->default(true);
            $table->enum('status', ['scheduled','boarding','departed','landed','cancelled'])->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['from_city', 'to_city', 'departure_date']);
        });

        Schema::create('flight_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flight_id')->constrained()->cascadeOnDelete();
            $table->json('passengers');
            $table->string('seat_class', 100);
            $table->integer('total_passengers');
            $table->string('booking_reference', 50)->unique();
            $table->timestamp('created_at')->useCurrent();
        });

        // ── 12. MODULE-SPECIFIC: eRent ────────────────────────────────────
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->foreignId('district_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['apartment','house','villa','room','office','shop']);
            $table->integer('bedrooms')->default(0);
            $table->integer('bathrooms')->default(0);
            $table->integer('kitchens')->default(0);
            $table->integer('living_rooms')->default(0);
            $table->string('furnishing', 50)->default('unfurnished');
            $table->decimal('area_sqm', 10, 2)->nullable();
            $table->integer('floor')->nullable();
            $table->unsignedSmallInteger('year_built')->nullable();
            $table->decimal('monthly_rent', 10, 2);
            $table->decimal('deposit', 10, 2)->default(0);
            $table->decimal('brokerage_fee', 10, 2)->default(0);
            $table->string('main_image')->nullable();
            $table->string('address')->nullable();
            $table->json('amenities')->nullable();
            $table->json('images')->nullable();
            $table->json('reels')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_booked')->default(false);
            $table->timestamps();
            $table->index(['district_id', 'type', 'is_available']);
        });

        Schema::create('property_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->enum('booking_type', ['full_rent', 'carbuun'])->default('full_rent');
            $table->enum('status', ['pending','confirmed','active','completed','cancelled','refund_requested','refunded'])->default('pending');
            $table->text('refund_reason')->nullable();
            $table->date('move_in_date')->nullable();
            $table->integer('duration_months')->default(1);
            $table->decimal('monthly_rent', 10, 2);
            $table->decimal('deposit', 10, 2)->default(0);
            $table->decimal('brokerage_fee', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('amount_remaining', 10, 2)->default(0);
            $table->text('cancel_reason')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        // ── 13. MODULE-SPECIFIC: eMoving ──────────────────────────────────
        Schema::create('moving_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_district_id')->constrained('districts')->cascadeOnDelete();
            $table->foreignId('to_district_id')->constrained('districts')->cascadeOnDelete();
            $table->enum('move_type', ['house','office','commercial','single_item']);
            $table->string('vehicle_type', 100)->default('standard');
            $table->decimal('base_price', 10, 2)->default(0);
            $table->decimal('price_per_room', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['from_district_id','to_district_id','move_type'], 'moving_dist_type_unique');
        });

        Schema::create('moving_extra_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_so')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('unit', 50)->default('item');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('moving_packages', function (Blueprint $table) {
            $table->id();
            $table->enum('move_type', ['commercial', 'office']);
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('includes')->nullable();
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ── 14. MODULE-SPECIFIC: eData ────────────────────────────────────
        Schema::create('data_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo')->nullable();
            $table->string('color', 20)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('data_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('data_providers')->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 50)->default('daily');
            $table->string('data_amount', 50);
            $table->string('speed', 50)->nullable();
            $table->integer('validity_days');
            $table->decimal('price', 10, 2);
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('data_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('data_providers')->cascadeOnDelete();
            $table->unsignedBigInteger('package_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('data_amount', 50)->nullable();
            $table->string('voice_minutes', 50)->nullable();
            $table->string('sms_count', 50)->nullable();
            $table->integer('validity_days');
            $table->decimal('price', 10, 2);
            $table->string('badge_label', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ── 15. MODULE-SPECIFIC: eExchange ────────────────────────────────
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('from_wallet', 100);
            $table->string('to_wallet', 100);
            $table->decimal('rate', 10, 6);
            $table->enum('fee_type', ['percentage', 'fixed']);
            $table->decimal('fee_value', 10, 2);
            $table->decimal('fee_percentage', 5, 2)->default(1.0);
            $table->decimal('min_amount', 10, 2)->default(0);
            $table->decimal('max_amount', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('updated_at')->useCurrent();
            $table->unique(['from_wallet', 'to_wallet']);
        });

        // ── 16. MODULE-SPECIFIC: eLaundry ─────────────────────────────────
        Schema::create('laundry_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_so')->nullable();
            $table->decimal('normal_price', 10, 2);
            $table->decimal('express_price', 10, 2);
            $table->integer('normal_days')->default(3);
            $table->integer('express_hours')->default(24);
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ── 17. MODULE-SPECIFIC: eParcel ──────────────────────────────────
        Schema::create('parcel_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('max_weight_kg', 10, 2)->nullable();
            $table->decimal('base_fee', 10, 2)->default(0);
            $table->decimal('fee_per_kg', 10, 2)->default(0);
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('delivery_zone_pricing', function (Blueprint $table) {
            $table->id();
            $table->string('module_id', 50);
            $table->foreignId('from_district_id')->constrained('districts')->cascadeOnDelete();
            $table->foreignId('to_district_id')->constrained('districts')->cascadeOnDelete();
            $table->decimal('base_price', 10, 2);
            $table->decimal('price_per_kg', 10, 2)->default(0);
            $table->decimal('per_km_price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['module_id', 'from_district_id', 'to_district_id'], 'dzp_mod_dist_unique');
        });

        // ── 18. MODULE-SPECIFIC: eHealth ──────────────────────────────────
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->string('specialization');
            $table->text('qualification')->nullable();
            $table->integer('experience_years')->default(0);
            $table->decimal('consultation_fee', 10, 2)->default(0);
            $table->string('avatar')->nullable();
            $table->string('image')->nullable();
            $table->text('bio')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['consultation','ambulance','home_nurse']);
            $table->dateTime('scheduled_at');
            $table->enum('status', ['pending','confirmed','completed','cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ── 19. eShop extras ──────────────────────────────────────────────
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('symbol', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('type', 20)->default('select');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained('product_attributes')->cascadeOnDelete();
            $table->string('value', 100);
            $table->string('color_code', 7)->nullable();
            $table->integer('sort_order')->default(0);
        });

        Schema::create('flash_deals', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('subtitle', 300)->nullable();
            $table->string('banner', 500)->nullable();
            $table->string('discount_type', 20)->default('percentage');
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('flash_deal_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flash_deal_id')->constrained('flash_deals')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('override_price', 10, 2)->nullable();
            $table->unique(['flash_deal_id', 'product_id']);
        });

        Schema::create('deals_of_day', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('badge', 50)->nullable();
            $table->string('discount_type', 20)->default('percentage');
            $table->decimal('discount_value', 8, 2)->default(0);
            $table->timestamp('ends_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('eshop_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('subtitle', 300)->nullable();
            $table->string('banner', 500)->nullable();
            $table->string('badge', 50)->nullable();
            $table->string('discount_type', 20)->default('percentage');
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('eshop_campaign_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('eshop_campaigns')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unique(['campaign_id', 'product_id']);
        });

        Schema::create('discount_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->enum('discount_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('badge_text', 100)->default('Special Offer');
            $table->string('badge_color', 30)->default('red');
            $table->boolean('apply_to_all')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('internal_notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['vendor_id', 'is_active']);
        });

        Schema::create('restaurant_favorites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('vendor_id');
            $table->timestamps();
            $table->unique(['user_id', 'vendor_id']);
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $tables = [
            'restaurant_favorites','discount_campaigns','eshop_campaign_products',
            'eshop_campaigns','deals_of_day','flash_deal_products','flash_deals',
            'product_attribute_values','product_attributes','product_units',
            'appointments','doctors','delivery_zone_pricing','parcel_types',
            'laundry_items','exchange_rates','data_bundles','data_packages',
            'data_providers','moving_packages','moving_extra_services','moving_pricing',
            'property_bookings','properties','flight_bookings','flights',
            'flight_routes','airlines','cart_items','carts','audit_logs','settings',
            'coupon_usages','wishlist','user_addresses','reviews','messages',
            'conversation_participants','conversations','push_notification_logs',
            'notifications','referrals','loyalty_points','campaigns','banners',
            'payment_transactions','commissions','withdrawal_requests','transactions',
            'wallets','order_tracking','order_status_history','order_items','orders',
            'coupons','deliveryman_earnings','deliveryman_documents','deliverymen',
            'product_addons','addons','product_variants','product_images','products',
            'categories','vendor_documents','vendor_employees','vendor_schedules',
            'vendors','user_permissions','personal_access_tokens','otp_codes','users',
            'module_district','modules','districts','role_permissions','permissions','roles',
        ];
        foreach ($tables as $t) {
            Schema::dropIfExists($t);
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
