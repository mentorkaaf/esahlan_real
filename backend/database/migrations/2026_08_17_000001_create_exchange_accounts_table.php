<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('exchange_accounts')) return;
        Schema::create('exchange_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            // evc | edahab | jeep | premier | ebesa | usdt
            $table->string('wallet_type', 20);
            $table->string('phone_number', 30);
            $table->string('label', 60)->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            // One account per wallet type per user
            $table->unique(['user_id', 'wallet_type']);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('exchange_accounts');
    }
};
