<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coin_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('coins');
            $table->unsignedInteger('bonus_coins')->default(0);
            $table->decimal('price', 8, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('badge_label')->nullable();   // e.g. "+10%", "HOT"
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_packages');
    }
};
