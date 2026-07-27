<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('module');                    // efood, eshop, egrocery, elaundry, eparcel
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name');
            $table->string('product_image')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->json('meta')->nullable();            // variant, options, etc.
            $table->timestamp('added_at');
            $table->timestamp('notified_30min_at')->nullable();
            $table->timestamp('notified_2h_at')->nullable();
            $table->timestamp('notified_24h_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
