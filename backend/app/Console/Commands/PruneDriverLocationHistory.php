<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * PruneDriverLocationHistory
 *
 * Runs hourly. Deletes rows older than 24 hours from driver_location_history.
 * Route polylines only show last 24 h on the admin map, so older rows are
 * wasted space.
 */
class PruneDriverLocationHistory extends Command
{
    protected $signature   = 'drivers:prune-location-history';
    protected $description = 'Delete driver GPS history older than 24 hours';

    public function handle(): int
    {
        $deleted = DB::table('driver_location_history')
            ->where('created_at', '<', now()->subHours(24))
            ->delete();

        $this->info("Deleted $deleted old driver location history rows.");
        return Command::SUCCESS;
    }
}
