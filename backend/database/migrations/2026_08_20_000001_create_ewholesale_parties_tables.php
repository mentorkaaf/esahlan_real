<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Suppliers ──────────────────────────────────────────────────────
        Schema::create('ewholesale_suppliers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $t->string('display_name');
            $t->string('logo')->nullable();
            $t->string('banner')->nullable();
            $t->text('about')->nullable();
            $t->string('warehouse_address')->nullable();
            $t->unsignedBigInteger('district_id')->nullable();
            $t->enum('verification', ['unverified','pending','verified','gold'])->default('unverified');
            $t->timestamp('verified_at')->nullable();
            $t->decimal('response_rate', 5, 2)->default(0);   // 0–100%
            $t->integer('response_time_avg')->default(0);       // minutes
            $t->decimal('rating', 3, 2)->default(0);
            $t->unsignedInteger('total_orders')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->unique('vendor_id');
            $t->index('verification');
            $t->index('is_active');
        });

        // ── Buyers ────────────────────────────────────────────────────────
        Schema::create('ewholesale_buyers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('business_name');
            $t->enum('business_type', ['shop','minimarket','restaurant','institution','reseller','other'])->default('shop');
            $t->string('license_no')->nullable();
            $t->string('license_doc')->nullable();
            $t->string('tax_id')->nullable();
            $t->enum('kyb_status', ['none','pending','approved','rejected'])->default('none');
            $t->timestamp('approved_at')->nullable();
            $t->unsignedBigInteger('default_payment_term_id')->nullable();
            $t->timestamps();

            $t->unique('user_id');
            $t->index('kyb_status');
        });

        // ── Credit Accounts ───────────────────────────────────────────────
        Schema::create('ewholesale_credit_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('buyer_id')->constrained('ewholesale_buyers')->cascadeOnDelete();
            $t->decimal('credit_limit', 12, 2)->default(0);
            $t->decimal('balance_used', 12, 2)->default(0);
            $t->enum('term', ['net7','net15','net30'])->default('net30');
            $t->enum('status', ['active','frozen'])->default('active');
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamps();

            $t->unique('buyer_id');
        });

        // ── Credit Ledger ─────────────────────────────────────────────────
        Schema::create('ewholesale_credit_ledger', function (Blueprint $t) {
            $t->id();
            $t->foreignId('credit_account_id')->constrained('ewholesale_credit_accounts')->cascadeOnDelete();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->enum('type', ['charge','payment','adjustment']);
            $t->decimal('amount', 12, 2);   // positive = charge, negative = payment/credit
            $t->decimal('balance_after', 12, 2);
            $t->date('due_date')->nullable();
            $t->string('note')->nullable();
            $t->timestamps();

            $t->index(['credit_account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ewholesale_credit_ledger');
        Schema::dropIfExists('ewholesale_credit_accounts');
        Schema::dropIfExists('ewholesale_buyers');
        Schema::dropIfExists('ewholesale_suppliers');
    }
};
