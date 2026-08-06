<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('global_orders', function (Blueprint $table) {
            $table->string('ship_zip', 20)->nullable()->default('')->change();
        });
    }

    public function down(): void
    {
        Schema::table('global_orders', function (Blueprint $table) {
            $table->string('ship_zip', 20)->nullable(false)->change();
        });
    }
};
