<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            // JSON array of egrocery_category IDs this coupon applies to.
            // NULL = applies to all categories.
            $table->json('category_ids')->nullable()->after('module_slug');
            $table->unsignedSmallInt('usage_per_user')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn('category_ids');
        });
    }
};
