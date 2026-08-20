<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ewholesale_banners', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('subtitle')->nullable();
            $t->string('image');
            $t->string('cta_label')->nullable();
            $t->string('cta_url')->nullable();
            $t->string('bg_color')->default('#1B1444');
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->timestamps();
            $t->index(['is_active', 'sort_order']);
        });

        // Add stock reservation column to variants for atomic checkout
        Schema::table('ewholesale_product_variants', function (Blueprint $t) {
            $t->decimal('reserved_qty', 12, 2)->default(0)->after('stock_qty');
        });
    }

    public function down(): void
    {
        Schema::table('ewholesale_product_variants', function (Blueprint $t) {
            $t->dropColumn('reserved_qty');
        });
        Schema::dropIfExists('ewholesale_banners');
    }
};
