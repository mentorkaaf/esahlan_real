<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds wants_tracking boolean to deliverymen.
 *
 * wants_tracking = the DRIVER'S INTENT to be tracked (set when they flip the
 * online toggle ON; cleared when they flip it OFF).
 *
 * is_online = whether the driver is CURRENTLY sending live location pings.
 *             The MarkStaleDriversOffline command clears this after 10 min of
 *             silence — but never touches wants_tracking.
 *
 * The PingOnlineDriversLocation scheduler uses wants_tracking=true to decide
 * who to FCM-ping, so that even if is_online was cleared by the stale command
 * the driver's phone still gets woken up to post its location.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliverymen', function (Blueprint $table) {
            // Default false — set to true only when driver consciously goes online
            $table->boolean('wants_tracking')->default(false)->after('is_online');
        });

        // Seed: any driver currently online OR recently active (last 2 hours)
        // is assumed to want tracking so we don't lose them on first deploy.
        DB::statement("
            UPDATE deliverymen
            SET    wants_tracking = 1
            WHERE  is_online = 1
               OR  (last_location_at IS NOT NULL AND last_location_at > NOW() - INTERVAL 2 HOUR)
        ");
    }

    public function down(): void
    {
        Schema::table('deliverymen', function (Blueprint $table) {
            $table->dropColumn('wants_tracking');
        });
    }
};
