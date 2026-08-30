<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add bonus_amount to main orders table if it exists
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'bonus_amount')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('bonus_amount', 8, 2)->default(0)->after('delivery_fee')
                    ->comment('Peak Pay Bonus charged to customer, credited to driver on delivery');
            });
        }

        // Add to global_orders if it exists
        if (Schema::hasTable('global_orders') && !Schema::hasColumn('global_orders', 'bonus_amount')) {
            $hasFee = Schema::hasColumn('global_orders', 'delivery_fee');
            Schema::table('global_orders', function (Blueprint $table) use ($hasFee) {
                $col = $table->decimal('bonus_amount', 8, 2)->default(0);
                if ($hasFee) {
                    $col->after('delivery_fee');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'bonus_amount')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('bonus_amount');
            });
        }
        if (Schema::hasTable('global_orders') && Schema::hasColumn('global_orders', 'bonus_amount')) {
            Schema::table('global_orders', function (Blueprint $table) {
                $table->dropColumn('bonus_amount');
            });
        }
    }
};
