<?php

namespace App\Console\Commands\HR;

use App\Services\HR\LeaveService;
use Illuminate\Console\Command;

class InitLeaveBalances extends Command
{
    protected $signature   = 'hr:init-leave-balances {year? : The year to initialise (default: current year)}';
    protected $description = 'Initialise leave balances for all active employees for a given year';

    public function handle(): int
    {
        $year    = (int) ($this->argument('year') ?? now()->year);
        $created = LeaveService::initBalancesForYear($year);

        $this->info("✓ Created {$created} leave balance records for {$year}.");

        return self::SUCCESS;
    }
}
