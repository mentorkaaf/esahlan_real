<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Categories (self-referencing 2-level tree) ─────────────────────
        Schema::create('egrocery_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('name', 120);
            $table->string('name_so', 120)->nullable();          // Somali name
            $table->string('slug', 120)->unique();
            $table->string('icon', 80)->nullable();              // emoji or icon key
            $table->string('image', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('egrocery_categories')->nullOnDelete();
        });

        // ── 2. Brands ─────────────────────────────────────────────────────────
        Schema::create('egrocery_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('logo', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── 3. Units (kg, g, L, ml, piece, pack, dozen, bag, carton) ─────────
        Schema::create('egrocery_units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();               // "kg", "piece"
            $table->string('abbreviation', 20)->nullable();     // "kg", "pc"
            $table->decimal('step', 8, 4)->default(1.0000);    // 0.25 for kg, 1 for piece
            $table->timestamps();
        });

        // ── 4. Products ───────────────────────────────────────────────────────
        Schema::create('egrocery_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('egrocery_categories')->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('egrocery_brands')->nullOnDelete();
            $table->string('name', 200);
            $table->string('name_so', 200)->nullable();
            $table->string('slug', 220)->unique();
            $table->text('description')->nullable();
            $table->json('images')->nullable();                  // array of URLs
            $table->foreignId('base_unit_id')->constrained('egrocery_units')->restrictOnDelete();
            $table->boolean('is_weight_based')->default(false); // true = sold by weight
            $table->json('tags')->nullable();                    // ["organic","fresh"]
            $table->string('barcode', 60)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->decimal('avg_rating', 3, 2)->default(0.00);
            $table->unsignedInteger('orders_count')->default(0); // denormalized
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
            $table->index(['is_featured', 'is_active']);
        });

        // ── 5. Product Variants ───────────────────────────────────────────────
        Schema::create('egrocery_product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('egrocery_products')->cascadeOnDelete();
            $table->string('label', 80);                         // "1 kg", "5 kg bag"
            $table->foreignId('unit_id')->constrained('egrocery_units')->restrictOnDelete();
            $table->decimal('unit_qty', 10, 3);                 // 1.000 / 5.000
            $table->decimal('price', 12, 2);
            $table->decimal('compare_price', 12, 2)->nullable(); // struck-through old price
            $table->decimal('cost', 12, 2)->nullable();          // purchase cost
            $table->string('sku', 80)->unique()->nullable();
            $table->decimal('stock_qty', 10, 3)->default(0.000);
            $table->decimal('low_stock_threshold', 10, 3)->default(5.000);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'is_default']);
            $table->index(['product_id', 'is_active']);
        });

        // ── 6. Stock Movements (audit log) ───────────────────────────────────
        Schema::create('egrocery_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('egrocery_product_variants')->cascadeOnDelete();
            $table->enum('type', ['purchase', 'sale', 'adjustment', 'return', 'waste']);
            $table->decimal('qty', 10, 3);                      // signed: negative = reduction
            $table->decimal('stock_before', 10, 3)->nullable();
            $table->decimal('stock_after', 10, 3)->nullable();
            $table->string('reference', 80)->nullable();         // order_no or note
            $table->text('note')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();  // user who made the move
            $table->timestamp('created_at')->useCurrent();

            $table->index(['variant_id', 'created_at']);
        });

        // ── 7. Banners ────────────────────────────────────────────────────────
        Schema::create('egrocery_banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200)->nullable();
            $table->string('image', 500);
            $table->enum('placement', ['home_top', 'home_mid'])->default('home_top');
            $table->enum('link_type', ['none', 'category', 'product', 'section', 'url'])->default('none');
            $table->string('link_value', 300)->nullable();       // id or URL
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── 8. Home Sections ─────────────────────────────────────────────────
        Schema::create('egrocery_sections', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('title_so', 120)->nullable();
            $table->enum('type', [
                'manual', 'flash_deal', 'best_sellers',
                'new_arrivals', 'buy_again', 'category_spotlight',
            ])->default('manual');
            $table->enum('layout', ['h_scroll', 'grid_2x', 'banner_list'])->default('h_scroll');
            $table->foreignId('category_id')->nullable()->constrained('egrocery_categories')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        // ── 9. Section ↔ Product pivot ────────────────────────────────────────
        Schema::create('egrocery_section_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('egrocery_sections')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('egrocery_products')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unique(['section_id', 'product_id']);
        });

        // ── 10. Flash Deals ───────────────────────────────────────────────────
        Schema::create('egrocery_flash_deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('egrocery_sections')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('egrocery_product_variants')->cascadeOnDelete();
            $table->decimal('deal_price', 12, 2);
            $table->unsignedInteger('qty_limit')->nullable();
            $table->unsignedInteger('qty_sold')->default(0);
            $table->timestamps();
            $table->unique(['section_id', 'variant_id']);
        });

        // ── 11. Delivery Zones ────────────────────────────────────────────────
        Schema::create('egrocery_delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->json('district_ids');                        // [1, 5, 12]
            $table->decimal('fee', 12, 2)->default(0.00);
            $table->decimal('min_order', 12, 2)->default(0.00);
            $table->decimal('free_over', 12, 2)->nullable();    // free delivery above this
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── 12. Delivery Slots ────────────────────────────────────────────────
        Schema::create('egrocery_delivery_slots', function (Blueprint $table) {
            $table->id();
            $table->string('label', 80);                         // "Today 2–4 PM"
            $table->unsignedTinyInteger('day_offset')->default(0); // 0=today, 1=tomorrow
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('capacity')->default(50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── 13. Orders ────────────────────────────────────────────────────────
        Schema::create('egrocery_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 30)->unique();            // EGR-2026-00001
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('address_id')->nullable(); // from user addresses
            $table->enum('status', [
                'pending', 'confirmed', 'picking', 'ready',
                'out_for_delivery', 'delivered', 'cancelled', 'refunded',
            ])->default('pending');
            $table->enum('payment_method', ['cash', 'wallet', 'evc'])->default('cash');
            $table->enum('payment_status', ['unpaid', 'paid', 'refunded'])->default('unpaid');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0.00);
            $table->decimal('delivery_fee', 12, 2)->default(0.00);
            $table->decimal('total', 12, 2);
            $table->string('coupon_code', 40)->nullable();
            $table->foreignId('delivery_slot_id')->nullable()->constrained('egrocery_delivery_slots')->nullOnDelete();
            $table->date('scheduled_date')->nullable();
            $table->enum('substitution_pref', ['call_me', 'best_match', 'refund_item'])->default('best_match');
            $table->text('customer_note')->nullable();
            $table->string('cancelled_reason', 300)->nullable();
            // Driver assignment via existing dispatch — driver_id reference only
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        // ── 14. Order Items ───────────────────────────────────────────────────
        Schema::create('egrocery_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('egrocery_orders')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('egrocery_product_variants')->restrictOnDelete();
            // Snapshots — item data at time of order (price may change later)
            $table->string('name_snapshot', 200);
            $table->string('unit_label_snapshot', 80);
            $table->decimal('unit_price_snapshot', 12, 2);
            $table->decimal('qty', 10, 3);
            $table->decimal('line_total', 12, 2);
            // Picking
            $table->decimal('picked_qty', 10, 3)->nullable();
            $table->foreignId('substitution_variant_id')->nullable()
                  ->constrained('egrocery_product_variants')->nullOnDelete();
            $table->enum('substitution_status', ['none', 'proposed', 'accepted', 'rejected'])
                  ->default('none');
            $table->timestamps();

            $table->index('order_id');
        });

        // ── 15. Shopping Lists ────────────────────────────────────────────────
        Schema::create('egrocery_shopping_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120)->default('My List');
            $table->timestamps();
            $table->index('user_id');
        });

        // ── 16. Shopping List Items ───────────────────────────────────────────
        Schema::create('egrocery_shopping_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained('egrocery_shopping_lists')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('egrocery_products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('egrocery_product_variants')->nullOnDelete();
            $table->decimal('qty', 10, 3)->default(1.000);
            $table->boolean('checked')->default(false);
            $table->timestamps();
        });

        // ── 17. Favorites ─────────────────────────────────────────────────────
        Schema::create('egrocery_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('egrocery_products')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'product_id']);
        });

        // ── 18. Reviews ───────────────────────────────────────────────────────
        Schema::create('egrocery_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('egrocery_products')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('egrocery_orders')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');               // 1–5
            $table->text('comment')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'order_id', 'product_id']); // one review per purchase
        });
    }

    public function down(): void
    {
        // Drop in reverse FK order
        Schema::dropIfExists('egrocery_reviews');
        Schema::dropIfExists('egrocery_favorites');
        Schema::dropIfExists('egrocery_shopping_list_items');
        Schema::dropIfExists('egrocery_shopping_lists');
        Schema::dropIfExists('egrocery_order_items');
        Schema::dropIfExists('egrocery_orders');
        Schema::dropIfExists('egrocery_delivery_slots');
        Schema::dropIfExists('egrocery_delivery_zones');
        Schema::dropIfExists('egrocery_flash_deals');
        Schema::dropIfExists('egrocery_section_products');
        Schema::dropIfExists('egrocery_sections');
        Schema::dropIfExists('egrocery_banners');
        Schema::dropIfExists('egrocery_stock_movements');
        Schema::dropIfExists('egrocery_product_variants');
        Schema::dropIfExists('egrocery_products');
        Schema::dropIfExists('egrocery_units');
        Schema::dropIfExists('egrocery_brands');
        Schema::dropIfExists('egrocery_categories');
    }
};
