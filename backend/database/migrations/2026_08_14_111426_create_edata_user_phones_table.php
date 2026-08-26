<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('edata_user_phones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('provider_id');
            $table->string('payment_phone', 20);   // telfoonka lacagta ka dira
            $table->string('data_phone', 20);       // telfoonka internetka loo rabo
            $table->timestamps();

            $table->unique(['user_id', 'provider_id']); // 1 row per user per provider
            $table->index('provider_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edata_user_phones');
    }
};
