<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add acceptance_status to orders table.
 *
 * Flow:
 *   Admin assigns order → acceptance_status = 'pending'  (driver not yet responded)
 *   Driver accepts       → acceptance_status = 'accepted' (order goes out_for_delivery)
 *   Driver declines      → acceptance_status = 'declined' (driver cleared, order re-queued)
 *   Pool self-pick       → acceptance_status = 'accepted' (driver picked themselves)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'acceptance_status')) {
                $table->enum('acceptance_status', ['pending', 'accepted', 'declined'])
                      ->nullable()
                      ->after('driver_accepted_at')
                      ->comment('null=not yet assigned, pending=waiting driver, accepted=driver accepted, declined=driver declined');
            }
            if (!Schema::hasColumn('orders', 'acceptance_responded_at')) {
                $table->timestamp('acceptance_responded_at')->nullable()->after('acceptance_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['acceptance_status', 'acceptance_responded_at']);
        });
    }
};
