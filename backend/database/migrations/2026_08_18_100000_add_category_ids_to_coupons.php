<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Only add category_ids if it doesn't already exist
        if (!Schema::hasColumn('coupons', 'category_ids')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->json('category_ids')->nullable()->after('module_slug');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('coupons', 'category_ids')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->dropColumn('category_ids');
            });
        }
    }
};
