<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Categories (2-level tree) ──────────────────────────────────────
        Schema::create('ewholesale_categories', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('parent_id')->nullable()->index();
            $t->string('name');
            $t->string('name_so')->nullable();
            $t->string('slug')->unique();
            $t->string('icon')->nullable();       // emoji or icon class
            $t->string('image')->nullable();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // ── Products ──────────────────────────────────────────────────────
        Schema::create('ewholesale_products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_id')->constrained('ewholesale_suppliers')->cascadeOnDelete();
            $t->foreignId('category_id')->constrained('ewholesale_categories');
            $t->string('name');
            $t->string('name_so')->nullable();
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->json('images')->nullable();               // array of paths
            $t->string('video_url')->nullable();
            $t->enum('unit', ['piece','dozen','carton','bag','sack','pallet','drum','box'])->default('carton');
            $t->unsignedSmallInteger('units_per_pack')->nullable();  // 1 carton = 24 pcs
            $t->decimal('moq', 12, 2)->default(1);
            $t->unsignedSmallInteger('lead_time_days')->default(0);
            $t->string('origin_country')->nullable();
            $t->string('brand')->nullable();
            $t->json('specs')->nullable();               // [{"key":"Weight","value":"25kg"}]
            $t->enum('status', ['draft','pending_review','active','rejected','archived'])->default('pending_review');
            $t->boolean('is_featured')->default(false);
            $t->unsignedInteger('orders_count')->default(0);
            // Denormalized from price_tiers for quick range display
            $t->decimal('min_price', 12, 2)->nullable();
            $t->decimal('max_price', 12, 2)->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->index(['supplier_id', 'status']);
            $t->index(['category_id', 'status']);
            $t->index('is_featured');
        });

        // ── Product Variants ──────────────────────────────────────────────
        Schema::create('ewholesale_product_variants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained('ewholesale_products')->cascadeOnDelete();
            $t->json('attributes')->nullable();   // {"size":"L","color":"Blue"}
            $t->string('sku')->nullable()->index();
            $t->decimal('stock_qty', 12, 2)->default(0);
            $t->decimal('weight_kg', 8, 3)->nullable();
            $t->decimal('volume_cbm', 8, 4)->nullable();
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->index(['product_id', 'is_active']);
        });

        // ── Price Tiers (the price-break ladder) ─────────────────────────
        Schema::create('ewholesale_price_tiers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained('ewholesale_products')->cascadeOnDelete();
            $t->unsignedBigInteger('variant_id')->nullable(); // null = all variants
            $t->decimal('min_qty', 12, 2);
            $t->decimal('max_qty', 12, 2)->nullable();       // null = unlimited
            $t->decimal('unit_price', 12, 2);
            $t->timestamps();

            $t->index(['product_id', 'min_qty']);
        });

        // ── Price Lists (buyer tiers: Retailer / Distributor / VIP) ──────
        Schema::create('ewholesale_price_lists', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->decimal('discount_percent', 5, 2)->default(0); // off tier price
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('ewholesale_buyer_price_lists', function (Blueprint $t) {
            $t->id();
            $t->foreignId('buyer_id')->constrained('ewholesale_buyers')->cascadeOnDelete();
            $t->foreignId('price_list_id')->constrained('ewholesale_price_lists')->cascadeOnDelete();
            $t->timestamps();

            $t->unique('buyer_id');
        });

        // ── Deals ─────────────────────────────────────────────────────────
        Schema::create('ewholesale_deals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained('ewholesale_products')->cascadeOnDelete();
            $t->decimal('deal_price_percent_off', 5, 2);
            $t->decimal('min_qty', 12, 2)->default(1);
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->unsignedInteger('qty_limit')->nullable();
            $t->unsignedInteger('qty_sold')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->index(['product_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ewholesale_deals');
        Schema::dropIfExists('ewholesale_buyer_price_lists');
        Schema::dropIfExists('ewholesale_price_lists');
        Schema::dropIfExists('ewholesale_price_tiers');
        Schema::dropIfExists('ewholesale_product_variants');
        Schema::dropIfExists('ewholesale_products');
        Schema::dropIfExists('ewholesale_categories');
    }
};
