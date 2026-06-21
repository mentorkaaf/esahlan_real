<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliverymen', function (Blueprint $table) {
            if (!Schema::hasColumn('deliverymen', 'is_online')) {
                $table->boolean('is_online')->default(false)->after('is_approved');
            }
            if (!Schema::hasColumn('deliverymen', 'is_available')) {
                $table->boolean('is_available')->default(false)->after('is_online');
            }
            if (!Schema::hasColumn('deliverymen', 'cash_in_hand')) {
                $table->decimal('cash_in_hand', 10, 2)->default(0)->after('total_deliveries');
            }
            if (!Schema::hasColumn('deliverymen', 'fcm_token')) {
                $table->string('fcm_token')->nullable()->after('cash_in_hand');
            }
        });
    }

    public function down(): void
    {
        Schema::table('deliverymen', function (Blueprint $table) {
            $table->dropColumn(['is_online', 'is_available', 'cash_in_hand', 'fcm_token']);
        });
    }
};
