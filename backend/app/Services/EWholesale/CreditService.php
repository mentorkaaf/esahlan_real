<?php

namespace App\Services\EWholesale;

use App\Models\EWholesale\{EWBuyer, EWCreditAccount, EWCreditLedger};
use Illuminate\Support\Facades\DB;

/**
 * CreditService — manages buyer credit accounts and the ledger.
 *
 * All mutations MUST be called from within the caller's DB::transaction
 * with lockForUpdate already held on the credit_account row.
 */
class CreditService
{
    // ── Read ──────────────────────────────────────────────────────────────

    /**
     * How much credit a buyer can still use.
     * Returns 0 if no active credit account.
     */
    public function availableCredit(EWBuyer $buyer): float
    {
        $account = $buyer->creditAccount;
        if (!$account || !$account->isActive()) return 0.0;
        return $account->availableCredit();
    }

    /**
     * Load the credit account with a write-lock for use inside a transaction.
     * Throws if no active account or insufficient credit.
     */
    public function lockForCharge(EWBuyer $buyer, float $amount): EWCreditAccount
    {
        $account = EWCreditAccount::where('buyer_id', $buyer->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($account->status !== 'active') {
            throw new \DomainException('CREDIT_ACCOUNT_FROZEN');
        }

        if ($account->availableCredit() < $amount) {
            throw new \DomainException('INSUFFICIENT_CREDIT:' . $account->availableCredit());
        }

        return $account;
    }

    // ── Write (must be inside caller's transaction) ────────────────────────

    /**
     * Charge (debit) credit account for an order.
     * Writes ledger entry and updates balance_used.
     * MUST be called inside a DB::transaction with lockForUpdate on account.
     */
    public function charge(EWCreditAccount $account, float $amount, int|null $orderId, string $note = ''): EWCreditLedger
    {
        $newBalance = round((float)$account->balance_used + $amount, 2);

        $account->update(['balance_used' => $newBalance]);

        $termDays = match ($account->term) {
            'net7'  => 7,
            'net15' => 15,
            'net30' => 30,
            default => 30,
        };

        return EWCreditLedger::create([
            'credit_account_id' => $account->id,
            'order_id'          => $orderId,
            'type'              => 'charge',
            'amount'            => $amount,
            'balance_after'     => $newBalance,
            'due_date'          => now()->addDays($termDays)->toDateString(),
            'note'              => $note ?: "Order #{$orderId} charged",
        ]);
    }

    /**
     * Settle (credit back) — for payment received or cancellation refund.
     * MUST be called inside a DB::transaction.
     */
    public function settle(EWCreditAccount $account, float $amount, int|null $orderId, string $note = ''): EWCreditLedger
    {
        $newBalance = round(max(0, (float)$account->balance_used - $amount), 2);

        $account->update(['balance_used' => $newBalance]);

        return EWCreditLedger::create([
            'credit_account_id' => $account->id,
            'order_id'          => $orderId,
            'type'              => 'payment',
            'amount'            => -$amount,  // negative = money back to buyer
            'balance_after'     => $newBalance,
            'due_date'          => null,
            'note'              => $note ?: "Payment settlement for order #{$orderId}",
        ]);
    }

    /**
     * Manual adjustment (admin use: corrections, write-offs).
     */
    public function adjust(EWCreditAccount $account, float $signedAmount, string $note): EWCreditLedger
    {
        $newBalance = round((float)$account->balance_used + $signedAmount, 2);
        $account->update(['balance_used' => max(0, $newBalance)]);

        return EWCreditLedger::create([
            'credit_account_id' => $account->id,
            'type'              => 'adjustment',
            'amount'            => $signedAmount,
            'balance_after'     => max(0, $newBalance),
            'note'              => $note,
        ]);
    }

    /**
     * Release credit held for a cancelled/rejected order.
     */
    public function releaseForOrder(int $orderId): void
    {
        $entry = EWCreditLedger::where('order_id', $orderId)->where('type', 'charge')->first();
        if (!$entry) return;

        $account = EWCreditAccount::lockForUpdate()->find($entry->credit_account_id);
        if ($account) {
            $this->settle($account, (float)$entry->amount, $orderId, "Credit released: order #{$orderId} cancelled");
        }
    }
}
