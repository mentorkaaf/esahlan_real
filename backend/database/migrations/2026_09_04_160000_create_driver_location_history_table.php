<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * driver_location_history
 *
 * Stores every location ping from every driver.
 * Used by the admin Live Driver Map to draw the driver's route polyline.
 *
 * Retention: 24 hours — a scheduled command prunes rows older than that.
 * Insert rate: ~1 row / 5 s per active driver (foreground service)
 *            + 1 row per FCM ping (~every 5 min, killed-app drivers)
 * Storage estimate: ~17,280 rows/day per active driver ≈ very manageable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_location_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliveryman_id')
                  ->constrained('deliverymen')
                  ->cascadeOnDelete();
            $table->decimal('latitude',  10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('created_at')->useCurrent();

            // Primary query: all points for a driver in the last N minutes
            $table->index(['deliveryman_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_location_history');
    }
};
