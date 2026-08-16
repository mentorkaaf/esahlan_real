<?php

namespace App\Console\Commands\HR;

use App\Services\HR\WorkforceService;
use Illuminate\Console\Command;

class ExpireWorkforceAssignments extends Command
{
    protected $signature   = 'hr:expire-assignments {--dry-run : Preview without making changes}';
    protected $description = 'End workforce assignments whose planned_end_date has passed and revoke module access';

    public function handle(): int
    {
        $this->info('Checking for overdue workforce assignments...');

        if ($this->option('dry-run')) {
            $count = \App\Models\HR\WorkforceAssignment::where('status', 'active')
                ->whereNotNull('planned_end_date')
                ->where('planned_end_date', '<', now()->toDateString())
                ->count();

            $this->warn("[DRY RUN] Would expire {$count} assignment(s). No changes made.");
            return 0;
        }

        $expired = WorkforceService::expireOverdue();

        if ($expired === 0) {
            $this->info('No overdue assignments found.');
        } else {
            $this->info("✓ Expired {$expired} assignment(s). Module access revoked.");
        }

        return 0;
    }
}
