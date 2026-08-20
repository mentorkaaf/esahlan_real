<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Shipping Rules ────────────────────────────────────────────────
        Schema::create('ewholesale_shipping_rules', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('supplier_id')->nullable(); // null = platform default
            $t->enum('basis', ['per_carton','per_kg','per_cbm','flat_by_zone'])->default('flat_by_zone');
            $t->decimal('rate', 10, 2);
            $t->json('zone_district_ids')->nullable(); // [1,2,3] district IDs this rule applies to
            $t->decimal('free_over', 12, 2)->nullable(); // free delivery if order >= this
            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->index('supplier_id');
        });

        // ── Saved Reorder Lists ───────────────────────────────────────────
        Schema::create('ewholesale_saved_lists', function (Blueprint $t) {
            $t->id();
            $t->foreignId('buyer_id')->constrained('ewholesale_buyers')->cascadeOnDelete();
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('ewholesale_saved_list_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('list_id')->constrained('ewholesale_saved_lists')->cascadeOnDelete();
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('variant_id')->nullable();
            $t->decimal('qty', 12, 2)->default(1);
            $t->timestamps();
        });

        // ── Activity / Audit Log ──────────────────────────────────────────
        Schema::create('ewholesale_activity_logs', function (Blueprint $t) {
            $t->id();
            $t->string('actor_type');         // 'user', 'admin', 'system'
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('action');             // e.g. 'quote.accepted', 'order.placed'
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->string('ip')->nullable();
            $t->timestamp('created_at')->useCurrent();

            $t->index(['actor_type', 'actor_id']);
            $t->index(['subject_type', 'subject_id']);
            $t->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ewholesale_activity_logs');
        Schema::dropIfExists('ewholesale_saved_list_items');
        Schema::dropIfExists('ewholesale_saved_lists');
        Schema::dropIfExists('ewholesale_shipping_rules');
    }
};
