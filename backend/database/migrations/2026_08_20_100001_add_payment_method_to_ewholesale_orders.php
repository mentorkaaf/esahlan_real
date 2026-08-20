<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ewholesale_orders', function (Blueprint $table) {
            $table->string('payment_method', 20)->default('waafi')->after('payment_plan');
        });
    }

    public function down(): void
    {
        Schema::table('ewholesale_orders', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
