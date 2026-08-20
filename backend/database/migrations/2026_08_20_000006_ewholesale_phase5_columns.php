<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — column additions for Trust, Comms & Polish.
 *
 * ewholesale_credit_ledger   → status (outstanding/overdue/settled)
 * ewholesale_suppliers       → on_time_delivery_rate, dispute_rate, cancellation_rate
 * ewholesale_reviews         → is_visible, hidden_reason, hidden_at
 * ewholesale_disputes        → sla_deadline, escalated_at, escalated_by
 * ewholesale_orders          → platform_fee_applied (actual amount charged)
 * ewholesale_suppliers       → platform_fee_percent override (NULL = use global setting)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Credit ledger: payment status tracking ─────────────────────────
        Schema::table('ewholesale_credit_ledger', function (Blueprint $t) {
            if (!Schema::hasColumn('ewholesale_credit_ledger', 'status')) {
                $t->enum('status', ['outstanding', 'overdue', 'settled'])
                  ->default('outstanding')
                  ->after('due_date');
            }
        });

        // ── Supplier scorecard columns ─────────────────────────────────────
        Schema::table('ewholesale_suppliers', function (Blueprint $t) {
            if (!Schema::hasColumn('ewholesale_suppliers', 'on_time_delivery_rate')) {
                $t->decimal('on_time_delivery_rate', 5, 2)->default(100)->after('response_time_avg');
            }
            if (!Schema::hasColumn('ewholesale_suppliers', 'dispute_rate')) {
                $t->decimal('dispute_rate', 5, 2)->default(0)->after('on_time_delivery_rate');
            }
            if (!Schema::hasColumn('ewholesale_suppliers', 'cancellation_rate')) {
                $t->decimal('cancellation_rate', 5, 2)->default(0)->after('dispute_rate');
            }
            if (!Schema::hasColumn('ewholesale_suppliers', 'platform_fee_percent')) {
                $t->decimal('platform_fee_percent', 5, 2)->nullable()->after('cancellation_rate');
            }
        });

        // ── Review moderation ──────────────────────────────────────────────
        Schema::table('ewholesale_reviews', function (Blueprint $t) {
            if (!Schema::hasColumn('ewholesale_reviews', 'is_visible')) {
                $t->boolean('is_visible')->default(true)->after('comment');
            }
            if (!Schema::hasColumn('ewholesale_reviews', 'hidden_reason')) {
                $t->string('hidden_reason')->nullable()->after('is_visible');
            }
            if (!Schema::hasColumn('ewholesale_reviews', 'hidden_at')) {
                $t->timestamp('hidden_at')->nullable()->after('hidden_reason');
            }
        });

        // ── Dispute SLA ────────────────────────────────────────────────────
        Schema::table('ewholesale_disputes', function (Blueprint $t) {
            if (!Schema::hasColumn('ewholesale_disputes', 'sla_deadline')) {
                // 72 hours by default, set on creation
                $t->timestamp('sla_deadline')->nullable()->after('status');
            }
            if (!Schema::hasColumn('ewholesale_disputes', 'escalated_at')) {
                $t->timestamp('escalated_at')->nullable()->after('sla_deadline');
            }
            if (!Schema::hasColumn('ewholesale_disputes', 'escalated_by')) {
                $t->unsignedBigInteger('escalated_by')->nullable()->after('escalated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ewholesale_credit_ledger', function (Blueprint $t) {
            $t->dropColumn(['status']);
        });
        Schema::table('ewholesale_suppliers', function (Blueprint $t) {
            $t->dropColumn(['on_time_delivery_rate','dispute_rate','cancellation_rate','platform_fee_percent']);
        });
        Schema::table('ewholesale_reviews', function (Blueprint $t) {
            $t->dropColumn(['is_visible','hidden_reason','hidden_at']);
        });
        Schema::table('ewholesale_disputes', function (Blueprint $t) {
            $t->dropColumn(['sla_deadline','escalated_at','escalated_by']);
        });
    }
};
