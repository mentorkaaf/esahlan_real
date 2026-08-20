<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── RFQs ──────────────────────────────────────────────────────────
        Schema::create('ewholesale_rfqs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('buyer_id')->constrained('ewholesale_buyers')->cascadeOnDelete();
            $t->string('title');
            $t->unsignedBigInteger('category_id')->nullable();
            $t->text('description');
            $t->decimal('qty', 12, 2);
            $t->string('unit')->default('carton');
            $t->decimal('target_price', 12, 2)->nullable();
            $t->date('needed_by')->nullable();
            $t->json('attachments')->nullable();
            $t->enum('status', ['open','closed','awarded','expired'])->default('open');
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();

            $t->index(['status', 'expires_at']);
            $t->index('buyer_id');
        });

        // ── RFQ Quotes (supplier responses) ──────────────────────────────
        Schema::create('ewholesale_rfq_quotes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rfq_id')->constrained('ewholesale_rfqs')->cascadeOnDelete();
            $t->foreignId('supplier_id')->constrained('ewholesale_suppliers')->cascadeOnDelete();
            $t->decimal('unit_price', 12, 2);
            $t->decimal('qty_offered', 12, 2);
            $t->unsignedSmallInteger('lead_time_days')->default(1);
            $t->date('valid_until')->nullable();
            $t->text('note')->nullable();
            $t->enum('status', ['sent','shortlisted','accepted','rejected','withdrawn'])->default('sent');
            $t->timestamps();

            $t->index(['rfq_id', 'status']);
        });

        // ── Inquiries (buyer asks product price) ──────────────────────────
        Schema::create('ewholesale_inquiries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('buyer_id')->constrained('ewholesale_buyers')->cascadeOnDelete();
            $t->foreignId('supplier_id')->constrained('ewholesale_suppliers')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('ewholesale_products')->cascadeOnDelete();
            $t->decimal('qty', 12, 2);
            $t->text('message');
            $t->unsignedBigInteger('chat_id')->nullable(); // FK → community_chats
            $t->timestamps();
        });

        // ── Formal Quotes (negotiation chain) ────────────────────────────
        Schema::create('ewholesale_quotes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('inquiry_id')->nullable();
            $t->unsignedBigInteger('rfq_quote_id')->nullable();
            $t->foreignId('buyer_id')->constrained('ewholesale_buyers')->cascadeOnDelete();
            $t->foreignId('supplier_id')->constrained('ewholesale_suppliers')->cascadeOnDelete();
            $t->json('lines'); // [{product_id,variant_id,qty,unit_price}]
            $t->decimal('subtotal', 12, 2);
            $t->decimal('delivery_fee', 12, 2)->default(0);
            $t->decimal('total', 12, 2);
            $t->string('payment_term')->default('prepaid'); // prepaid|deposit|net7|net15|net30
            $t->date('valid_until')->nullable();
            $t->enum('status', ['draft','sent','countered','accepted','declined','expired'])->default('draft');
            $t->unsignedBigInteger('counter_of_id')->nullable(); // negotiation chain
            $t->timestamp('accepted_at')->nullable();
            $t->timestamps();
        });

        // ── Orders ────────────────────────────────────────────────────────
        Schema::create('ewholesale_orders', function (Blueprint $t) {
            $t->id();
            $t->string('order_no')->unique();
            $t->foreignId('buyer_id')->constrained('ewholesale_buyers')->cascadeOnDelete();
            $t->foreignId('supplier_id')->constrained('ewholesale_suppliers')->cascadeOnDelete();
            $t->enum('source', ['cart','quote','rfq'])->default('cart');
            $t->unsignedBigInteger('quote_id')->nullable();
            $t->enum('status', [
                'pending_confirmation','confirmed','awaiting_payment','processing',
                'ready','partially_shipped','shipped','delivered','completed',
                'cancelled','disputed'
            ])->default('pending_confirmation');
            $t->enum('payment_plan', ['prepaid','deposit','credit'])->default('prepaid');
            $t->decimal('deposit_percent', 5, 2)->nullable();
            $t->decimal('subtotal', 12, 2);
            $t->decimal('delivery_fee', 12, 2)->default(0);
            $t->decimal('platform_fee', 12, 2)->default(0);
            $t->decimal('total', 12, 2);
            $t->decimal('paid_total', 12, 2)->default(0);
            $t->enum('fulfillment', ['delivery','pickup'])->default('delivery');
            $t->unsignedBigInteger('address_id')->nullable();
            $t->date('expected_at')->nullable();
            $t->text('buyer_note')->nullable();
            $t->string('cancelled_reason')->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->index(['buyer_id', 'status']);
            $t->index(['supplier_id', 'status']);
            $t->index('status');
        });

        // ── Order Items ────────────────────────────────────────────────────
        Schema::create('ewholesale_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained('ewholesale_orders')->cascadeOnDelete();
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('variant_id')->nullable();
            $t->string('name_snapshot');
            $t->string('unit_snapshot')->default('carton');
            $t->decimal('qty', 12, 2);
            $t->decimal('unit_price_snapshot', 12, 2); // tier-resolved at order time
            $t->decimal('line_total', 12, 2);
            $t->decimal('shipped_qty', 12, 2)->default(0);
            $t->timestamps();

            $t->index('order_id');
        });

        // ── Order Payments ────────────────────────────────────────────────
        Schema::create('ewholesale_order_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained('ewholesale_orders')->cascadeOnDelete();
            $t->enum('type', ['deposit','balance','full','credit_settlement','refund']);
            $t->enum('method', ['wallet','evc','cash','bank','credit']);
            $t->decimal('amount', 12, 2);
            $t->string('ref')->nullable();
            $t->enum('status', ['pending','confirmed','failed'])->default('confirmed');
            $t->timestamp('paid_at')->nullable();
            $t->string('note')->nullable();
            $t->unsignedBigInteger('recorded_by')->nullable(); // admin user id
            $t->timestamps();

            $t->index('order_id');
        });

        // ── Shipments (partial shipment support) ──────────────────────────
        Schema::create('ewholesale_shipments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained('ewholesale_orders')->cascadeOnDelete();
            $t->string('shipment_no')->unique();
            $t->json('items'); // [{item_id, qty}]
            $t->enum('status', ['preparing','dispatched','delivered'])->default('preparing');
            $t->unsignedBigInteger('dispatch_id')->nullable(); // existing dispatch system integration
            $t->timestamp('dispatched_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->string('note')->nullable();
            $t->timestamps();
        });

        // ── Disputes ──────────────────────────────────────────────────────
        Schema::create('ewholesale_disputes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained('ewholesale_orders')->cascadeOnDelete();
            $t->unsignedBigInteger('opened_by'); // user_id
            $t->string('reason');
            $t->text('description');
            $t->json('attachments')->nullable();
            $t->enum('status', ['open','supplier_responded','resolved_refund','resolved_release','closed'])->default('open');
            $t->text('resolution_note')->nullable();
            $t->unsignedBigInteger('resolved_by')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
        });

        // ── Reviews ───────────────────────────────────────────────────────
        Schema::create('ewholesale_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained('ewholesale_orders')->cascadeOnDelete();
            $t->foreignId('buyer_id')->constrained('ewholesale_buyers')->cascadeOnDelete();
            $t->foreignId('supplier_id')->constrained('ewholesale_suppliers')->cascadeOnDelete();
            $t->unsignedBigInteger('product_id')->nullable();
            $t->unsignedTinyInteger('rating'); // 1–5
            $t->text('comment')->nullable();
            $t->timestamps();

            $t->unique(['order_id','buyer_id']);
        });

        // ── Settings ─────────────────────────────────────────────────────
        Schema::create('ewholesale_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->string('type')->default('string'); // string|json|boolean|integer|decimal
            $t->string('label')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ewholesale_settings');
        Schema::dropIfExists('ewholesale_reviews');
        Schema::dropIfExists('ewholesale_disputes');
        Schema::dropIfExists('ewholesale_shipments');
        Schema::dropIfExists('ewholesale_order_payments');
        Schema::dropIfExists('ewholesale_order_items');
        Schema::dropIfExists('ewholesale_orders');
        Schema::dropIfExists('ewholesale_quotes');
        Schema::dropIfExists('ewholesale_inquiries');
        Schema::dropIfExists('ewholesale_rfq_quotes');
        Schema::dropIfExists('ewholesale_rfqs');
    }
};
