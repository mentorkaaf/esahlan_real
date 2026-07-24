<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_pay_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // "EVC Plus", "Waafi", "Sahal"
            $table->string('account_number');                // "090376464"
            $table->string('ussd_template');                 // "*789*090376464*{amount}#"
            $table->text('instructions')->nullable();        // Human-readable instructions
            $table->string('icon')->nullable();              // Emoji: "📱", "💚"
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_pay_accounts');
    }
};
