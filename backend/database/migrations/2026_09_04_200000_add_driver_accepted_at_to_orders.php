<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add driver_accepted_at to orders table.
 *
 * Distinguishes between:
 *   - Admin pre-assigned: deliveryman_id set, driver_accepted_at NULL
 *   - Driver accepted:    deliveryman_id set, driver_accepted_at NOT NULL
 *
 * My Deliveries tab now filters by driver_accepted_at IS NOT NULL,
 * so pre-assigned orders don't appear until the driver taps Accept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('driver_accepted_at')->nullable()->after('dispatched_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('driver_accepted_at');
        });
    }
};
