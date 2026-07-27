<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Products: popularity tracking
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'view_count')) {
                $table->unsignedBigInteger('view_count')->default(0)->after('total_reviews');
            }
            if (!Schema::hasColumn('products', 'sales_count')) {
                $table->unsignedBigInteger('sales_count')->default(0)->after('view_count');
            }
        });

        // Product reviews: use existing polymorphic `reviews` table (reviewable_type = App\Models\Product)
        // No new table needed.

        // Vendors: eShop-specific meta stored in existing `meta` JSON column.
        // commission_type + commission_value already exist.
        // No schema change needed for vendors.
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumnIfExists('view_count');
            $table->dropColumnIfExists('sales_count');
        });
    }
};
