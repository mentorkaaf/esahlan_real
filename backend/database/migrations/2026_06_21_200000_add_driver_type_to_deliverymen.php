<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliverymen', function (Blueprint $table) {
            if (!Schema::hasColumn('deliverymen', 'driver_type')) {
                $table->string('driver_type', 20)->default('normal')->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('deliverymen', function (Blueprint $table) {
            $table->dropColumn('driver_type');
        });
    }
};
