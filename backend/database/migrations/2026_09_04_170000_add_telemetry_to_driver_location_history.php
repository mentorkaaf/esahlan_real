<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds speed, heading, accuracy, and battery_level to driver_location_history.
 * These are sent by the Flutter app on every GPS ping and allow the admin map
 * to show: direction arrows, speed badges, battery warnings, and speed-colored
 * trail segments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_location_history', function (Blueprint $table) {
            $table->float('speed',    8, 2)->nullable()->after('longitude');   // m/s from GPS
            $table->float('heading',  8, 2)->nullable()->after('speed');       // 0-360 degrees
            $table->float('accuracy', 8, 2)->nullable()->after('heading');     // metres
            $table->tinyInteger('battery_level')->nullable()->after('accuracy'); // 0-100%
        });

        Schema::table('deliverymen', function (Blueprint $table) {
            $table->tinyInteger('battery_level')->nullable()->after('wants_tracking');
            $table->float('speed',   8, 2)->nullable()->after('battery_level'); // last known speed
            $table->float('heading', 8, 2)->nullable()->after('speed');         // last known heading
            $table->unsignedSmallInteger('missed_pings')->default(0)->after('heading');
            // missed_pings: incremented by PingOnlineDriversLocation when driver doesn't respond;
            // reset to 0 on every successful location ping. Used for reliability score.
        });
    }

    public function down(): void
    {
        Schema::table('driver_location_history', function (Blueprint $table) {
            $table->dropColumn(['speed', 'heading', 'accuracy', 'battery_level']);
        });
        Schema::table('deliverymen', function (Blueprint $table) {
            $table->dropColumn(['battery_level', 'speed', 'heading', 'missed_pings']);
        });
    }
};
