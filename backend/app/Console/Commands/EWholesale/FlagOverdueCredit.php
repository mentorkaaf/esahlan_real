<?php

namespace App\Console\Commands\EWholesale;

use App\Models\EWholesale\{EWCreditAccount, EWCreditLedger, EWSetting};
use App\Jobs\EWholesale\SendCreditReminderJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Log};

/**
 * ewholesale:flag-overdue-credit — nightly credit collections job.
 *
 * 1. Marks charge ledger entries as overdue when due_date < today
 * 2. Auto-freezes credit accounts when overdue > freeze_after_days (setting, default 14)
 * 3. Dispatches SendCreditReminderJob for newly overdue accounts
 *
 * Scheduled: daily at 08:00
 */
class FlagOverdueCredit extends Command
{
    protected $signature   = 'ewholesale:flag-overdue-credit';
    protected $description = 'Flag overdue credit ledger entries and auto-freeze accounts';

    public function handle(): int
    {
        $freezeAfter = (int) EWSetting::get('credit_freeze_after_days', 14);

        // ── Step 1: flag newly overdue charge entries ──────────────────────
        $flagged = EWCreditLedger::where('type', 'charge')
            ->where('status', 'outstanding')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);

        $this->info("{$flagged} ledger entries flagged as overdue.");

        // ── Step 2: auto-freeze accounts with old overdue entries ──────────
        $freezeDate = now()->subDays($freezeAfter)->toDateString();

        $accountsToFreeze = EWCreditLedger::where('type', 'charge')
            ->where('status', 'overdue')
            ->where('due_date', '<', $freezeDate)
            ->whereHas('creditAccount', fn($q) => $q->where('status', 'active'))
            ->with('creditAccount')
            ->get()
            ->pluck('credit_account_id')
            ->unique();

        $frozen = 0;
        foreach ($accountsToFreeze as $accountId) {
            EWCreditAccount::where('id', $accountId)
                ->where('status', 'active')
                ->update(['status' => 'frozen']);
            $frozen++;
            Log::info('EW credit account auto-frozen', ['account_id' => $accountId]);
        }

        $this->info("{$frozen} credit accounts auto-frozen.");

        // ── Step 3: dispatch reminder job for overdue (not yet frozen) ──────
        $remindAccounts = EWCreditAccount::where('status', 'active')
            ->whereHas('ledger', fn($q) => $q
                ->where('type', 'charge')
                ->where('status', 'overdue')
            )
            ->with('buyer.user')
            ->get();

        foreach ($remindAccounts as $account) {
            SendCreditReminderJob::dispatch($account->id);
        }

        $this->info("{$remindAccounts->count()} reminder jobs dispatched.");

        return self::SUCCESS;
    }
}
