<?php

namespace App\Console\Commands\EWholesale;

use App\Models\EWholesale\{EWQuote, EWRfq};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ewholesale:expire — auto-expire quotes past valid_until and RFQs past expires_at.
 * Scheduled: every 30 minutes.
 */
class ExpireQuotesAndRfqs extends Command
{
    protected $signature   = 'ewholesale:expire';
    protected $description = 'Expire overdue quotes and RFQs';

    public function handle(): int
    {
        // Expire quotes
        $quoteCount = EWQuote::where('status', 'sent')
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', now()->toDateString())
            ->update(['status' => 'expired']);

        // Also expire countered quotes that haven't been actioned
        $counterCount = EWQuote::where('status', 'countered')
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', now()->subDays(3)->toDateString()) // 3-day grace for counters
            ->update(['status' => 'expired']);

        // Expire open RFQs
        $rfqCount = EWRfq::where('status', 'open')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        $this->info("Expired: {$quoteCount} quotes, {$counterCount} counter-quotes, {$rfqCount} RFQs");

        return self::SUCCESS;
    }
}
