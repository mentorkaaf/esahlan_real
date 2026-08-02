<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Subscriptions (premium / gold monthly plans)
        Schema::create('emarry_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('plan', ['premium', 'gold']);
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->enum('payment_method', ['waafi_pay', 'epay', 'mobile_pay']);
            $table->string('payment_reference')->nullable();
            $table->decimal('amount', 8, 2);
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['user_id', 'status', 'expires_at']);
        });

        // Credit wallets (one row per user)
        Schema::create('emarry_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('balance')->default(0);
            $table->timestamps();
            $table->unique('user_id');
        });

        // Credit transaction ledger
        Schema::create('emarry_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('amount');          // +credits or -credits
            $table->string('type');             // purchase|super_like|boost|undo
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->decimal('paid_amount', 8, 2)->nullable();
            $table->string('description');
            $table->timestamps();
            $table->index('user_id');
        });

        // Pending mobile-pay verifications (admin approves manually)
        Schema::create('emarry_mobile_pay_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('item_type', ['subscription', 'credits']);
            $table->string('item_key');         // plan slug or credit package key
            $table->decimal('amount', 8, 2);
            $table->string('sender_phone');
            $table->string('screenshot_url')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emarry_mobile_pay_requests');
        Schema::dropIfExists('emarry_credit_transactions');
        Schema::dropIfExists('emarry_credits');
        Schema::dropIfExists('emarry_subscriptions');
    }
};
