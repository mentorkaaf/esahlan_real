<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Global Settings ──────────────────────────────────────────────────
        Schema::create('global_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // ── Global Categories ────────────────────────────────────────────────
        Schema::create('global_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('global_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('image')->nullable();
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Global Products ──────────────────────────────────────────────────
        Schema::create('global_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('global_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('compare_price', 10, 2)->nullable(); // original/crossed price
            $table->decimal('cost_price', 10, 2)->nullable();    // for margin calc
            $table->string('sku')->nullable()->unique();
            $table->integer('stock')->default(0);
            $table->boolean('track_stock')->default(true);
            $table->enum('type', ['physical', 'dropship'])->default('physical');
            $table->string('supplier_name')->nullable();
            $table->string('supplier_product_id')->nullable(); // CJ/AliExpress ID
            $table->decimal('weight_kg', 8, 3)->nullable();
            $table->string('origin_country')->nullable()->default('CN');
            $table->string('thumbnail')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_new_arrival')->default(false);
            $table->boolean('is_bestseller')->default(false);
            $table->integer('sold_count')->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('review_count')->default(0);
            $table->json('tags')->nullable();
            $table->json('shipping_info')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Product Images ───────────────────────────────────────────────────
        Schema::create('global_product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('global_products')->cascadeOnDelete();
            $table->string('url');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ── Product Variants ─────────────────────────────────────────────────
        Schema::create('global_product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('global_products')->cascadeOnDelete();
            $table->string('name');          // e.g. "Size / Color"
            $table->string('value');         // e.g. "XL / Red"
            $table->decimal('price_modifier', 10, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->string('sku')->nullable();
            $table->string('image')->nullable();
            $table->timestamps();
        });

        // ── Global Users (international customers) ───────────────────────────
        Schema::create('global_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('avatar')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->boolean('email_verified')->default(false);
            $table->string('email_verify_token')->nullable();
            $table->string('reset_token')->nullable();
            $table->timestamp('reset_token_expires_at')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->string('paypal_customer_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('marketing_emails')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Global Addresses ─────────────────────────────────────────────────
        Schema::create('global_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('global_user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->default('Home'); // Home, Work, etc.
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone')->nullable();
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('zip_code');
            $table->string('country_code', 2)->default('US');
            $table->string('country_name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // ── Global Orders ────────────────────────────────────────────────────
        Schema::create('global_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('global_user_id')->constrained()->restrictOnDelete();
            // Shipping address snapshot
            $table->string('ship_first_name');
            $table->string('ship_last_name');
            $table->string('ship_phone')->nullable();
            $table->string('ship_address_line1');
            $table->string('ship_address_line2')->nullable();
            $table->string('ship_city');
            $table->string('ship_state')->nullable();
            $table->string('ship_zip');
            $table->string('ship_country_code', 2);
            $table->string('ship_country_name');
            // Financials
            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('USD');
            // Status
            $table->enum('status', [
                'pending', 'paid', 'processing', 'shipped',
                'delivered', 'cancelled', 'refunded', 'on_hold'
            ])->default('pending');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->enum('fulfillment_status', ['unfulfilled', 'partial', 'fulfilled', 'shipped', 'delivered'])->default('unfulfilled');
            // Payment
            $table->enum('payment_method', ['stripe', 'paypal'])->nullable();
            $table->string('payment_intent_id')->nullable(); // Stripe
            $table->string('paypal_order_id')->nullable();
            // Shipping
            $table->string('tracking_number')->nullable();
            $table->string('shipping_carrier')->nullable();
            $table->string('tracking_url')->nullable();
            $table->timestamp('estimated_delivery_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Global Order Items ───────────────────────────────────────────────
        Schema::create('global_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('global_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('global_product_id')->constrained()->restrictOnDelete();
            $table->foreignId('global_product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');   // snapshot
            $table->string('variant_name')->nullable();
            $table->string('product_image')->nullable();
            $table->string('sku')->nullable();
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->enum('type', ['physical', 'dropship'])->default('physical');
            $table->string('supplier_name')->nullable();
            $table->string('supplier_order_id')->nullable();
            $table->enum('fulfillment_status', ['pending', 'ordered', 'shipped', 'delivered'])->default('pending');
            $table->timestamps();
        });

        // ── Global Payments ──────────────────────────────────────────────────
        Schema::create('global_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('global_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('global_user_id')->constrained()->cascadeOnDelete();
            $table->enum('method', ['stripe', 'paypal']);
            $table->string('transaction_id')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded', 'partially_refunded']);
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->string('refund_id')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamps();
        });

        // ── Global Shipping Zones ────────────────────────────────────────────
        Schema::create('global_shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');                // e.g. "USA Standard"
            $table->json('countries');             // ["US", "CA"]
            $table->decimal('flat_rate', 10, 2)->default(0);
            $table->decimal('free_shipping_over', 10, 2)->nullable();
            $table->integer('estimated_days_min')->default(7);
            $table->integer('estimated_days_max')->default(21);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Global Wishlists ─────────────────────────────────────────────────
        Schema::create('global_wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('global_user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('global_product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['global_user_id', 'global_product_id']);
        });

        // ── Global Reviews ───────────────────────────────────────────────────
        Schema::create('global_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('global_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('global_user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('global_order_id')->nullable()->constrained()->nullOnDelete();
            $table->tinyInteger('rating');    // 1-5
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->json('images')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->boolean('is_verified_purchase')->default(false);
            $table->timestamps();
        });

        // ── Global Coupons ───────────────────────────────────────────────────
        Schema::create('global_coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('value', 10, 2);
            $table->decimal('minimum_order', 10, 2)->default(0);
            $table->decimal('maximum_discount', 10, 2)->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('used_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Seed default settings ────────────────────────────────────────────
        $settings = [
            // Payment toggles
            ['key' => 'global_stripe_enabled',      'value' => '0'],
            ['key' => 'global_stripe_test_mode',    'value' => '1'],
            ['key' => 'global_stripe_pk_live',      'value' => ''],
            ['key' => 'global_stripe_sk_live',      'value' => ''],
            ['key' => 'global_stripe_pk_test',      'value' => ''],
            ['key' => 'global_stripe_sk_test',      'value' => ''],
            ['key' => 'global_stripe_webhook_secret','value' => ''],
            ['key' => 'global_paypal_enabled',      'value' => '0'],
            ['key' => 'global_paypal_test_mode',    'value' => '1'],
            ['key' => 'global_paypal_client_id_live','value' => ''],
            ['key' => 'global_paypal_secret_live',  'value' => ''],
            ['key' => 'global_paypal_client_id_test','value' => ''],
            ['key' => 'global_paypal_secret_test',  'value' => ''],
            // Store settings
            ['key' => 'global_store_name',          'value' => 'eSahlan Global'],
            ['key' => 'global_store_email',         'value' => 'global@esahlan.com'],
            ['key' => 'global_default_currency',    'value' => 'USD'],
            ['key' => 'global_supported_currencies','value' => 'USD,EUR,GBP'],
            ['key' => 'global_tax_rate',            'value' => '0'],
            ['key' => 'global_free_shipping_over',  'value' => '50'],
            ['key' => 'global_maintenance_mode',    'value' => '0'],
            // Feature toggles
            ['key' => 'global_reviews_enabled',     'value' => '1'],
            ['key' => 'global_coupons_enabled',     'value' => '1'],
            ['key' => 'global_wishlist_enabled',    'value' => '1'],
        ];

        foreach ($settings as $s) {
            DB::table('global_settings')->insert(array_merge($s, [
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }

        // Default shipping zones
        DB::table('global_shipping_zones')->insert([
            [
                'name'                 => 'United States',
                'countries'            => json_encode(['US']),
                'flat_rate'            => 5.99,
                'free_shipping_over'   => 50.00,
                'estimated_days_min'   => 5,
                'estimated_days_max'   => 10,
                'is_active'            => true,
                'created_at'           => now(),
                'updated_at'           => now(),
            ],
            [
                'name'                 => 'Europe',
                'countries'            => json_encode(['GB','DE','FR','IT','ES','NL','BE','AT','SE','NO','DK','FI','PL','PT','IE','CH']),
                'flat_rate'            => 9.99,
                'free_shipping_over'   => 75.00,
                'estimated_days_min'   => 7,
                'estimated_days_max'   => 14,
                'is_active'            => true,
                'created_at'           => now(),
                'updated_at'           => now(),
            ],
            [
                'name'                 => 'Rest of World',
                'countries'            => json_encode(['*']),
                'flat_rate'            => 14.99,
                'free_shipping_over'   => 100.00,
                'estimated_days_min'   => 14,
                'estimated_days_max'   => 30,
                'is_active'            => true,
                'created_at'           => now(),
                'updated_at'           => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('global_reviews');
        Schema::dropIfExists('global_wishlists');
        Schema::dropIfExists('global_payments');
        Schema::dropIfExists('global_order_items');
        Schema::dropIfExists('global_orders');
        Schema::dropIfExists('global_addresses');
        Schema::dropIfExists('global_users');
        Schema::dropIfExists('global_product_variants');
        Schema::dropIfExists('global_product_images');
        Schema::dropIfExists('global_products');
        Schema::dropIfExists('global_categories');
        Schema::dropIfExists('global_shipping_zones');
        Schema::dropIfExists('global_coupons');
        Schema::dropIfExists('global_settings');
    }
};
