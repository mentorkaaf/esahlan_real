<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('exchange_orders')) return;
        Schema::create('exchange_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('from_wallet', 30);
            $table->string('to_wallet', 30);
            $table->decimal('sent_amount', 12, 2);
            $table->decimal('fee_amount', 12, 2)->default(0);
            $table->decimal('rate', 12, 6)->default(1);
            $table->decimal('converted_amount', 12, 2);
            $table->string('recipient_phone', 30);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('completed');
            $table->text('note')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('exchange_orders');
    }
};
