<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Deliveryman;
use Carbon\Carbon;

/**
 * MarkStaleDriversOffline
 *
 * Runs every 5 minutes via the scheduler.
 * Marks drivers as offline when their last location ping is older than
 * 10 minutes — the foreground service posts every 5 s, so a 10-minute
 * gap means the service has definitely died and the driver is no longer
 * actively tracking.
 */
class MarkStaleDriversOffline extends Command
{
    protected $signature   = 'drivers:mark-stale-offline';
    protected $description = 'Mark drivers offline if no location ping for 10+ minutes';

    public function handle(): int
    {
        $cutoff = Carbon::now()->subMinutes(10);

        $count = Deliveryman::where('is_online', true)
            ->where(function ($q) use ($cutoff) {
                // No location ever, or last location is old
                $q->whereNull('last_location_at')
                  ->orWhere('last_location_at', '<', $cutoff);
            })
            ->update([
                'is_online' => false,
                'status'    => \DB::raw("CASE WHEN status = 'busy' THEN 'busy' ELSE 'available' END"),
                // Keep status=busy for drivers with active orders
            ]);

        // Also reset drivers who are marked 'busy' but have no active orders
        // and haven't pinged in 10 min — avoids ghost "busy" state
        Deliveryman::where('status', 'busy')
            ->where('last_location_at', '<', $cutoff)
            ->whereNotExists(function ($q) {
                $q->from('orders')
                  ->whereColumn('orders.deliveryman_id', 'deliverymen.id')
                  ->whereIn('orders.status', ['out_for_delivery', 'ready_for_pickup', 'confirmed']);
            })
            ->update(['status' => 'available', 'is_online' => false]);

        $this->info("Marked $count stale drivers as offline.");
        return Command::SUCCESS;
    }
}
