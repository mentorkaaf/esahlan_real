<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->decimal('delivery_fee', 10, 2)->default(0)->after('vendor_earning');
            $table->decimal('delivery_fee_commission', 10, 2)->default(0)->after('delivery_fee');
        });

        // Seed setting: delivery fee commission rate (% admin takes from delivery fee)
        DB::table('settings')->insertOrIgnore([
            'key'   => 'delivery_fee_commission_pct',
            'value' => '0',
            'type'  => 'integer',
            'group' => 'delivery',
        ]);
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropColumn(['delivery_fee', 'delivery_fee_commission']);
        });
        DB::table('settings')->where('key', 'delivery_fee_commission_pct')->delete();
    }
};
