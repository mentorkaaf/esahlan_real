<?php

namespace App\Jobs\EWholesale;

use App\Models\EWholesale\{EWCreditAccount, EWCreditLedger};
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

/**
 * SendCreditReminderJob — FCM push to buyer with overdue credit balance.
 * Also serves as the "send reminder" action from the admin aging report.
 */
class SendCreditReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly int $creditAccountId) {}

    public function handle(FcmService $fcm): void
    {
        $account = EWCreditAccount::with('buyer.user')->find($this->creditAccountId);
        if (!$account) return;

        $overdueEntries = EWCreditLedger::where('credit_account_id', $account->id)
            ->where('type', 'charge')
            ->where('status', 'overdue')
            ->get();

        if ($overdueEntries->isEmpty()) return;

        $totalOverdue = $overdueEntries->sum(fn($e) => abs((float) $e->amount));
        $oldestDue    = $overdueEntries->min('due_date');

        $title = 'Payment Overdue';
        $body  = 'You have $' . number_format($totalOverdue, 2)
            . ' in overdue credit payments. Please settle to keep your account active.';

        // FCM to buyer
        $user = $account->buyer?->user;
        if ($user?->fcm_token) {
            try {
                $fcm->sendToTokens(
                    tokens: [$user->fcm_token],
                    title:  $title,
                    body:   $body,
                    data:   [
                        'type'          => 'credit_overdue',
                        'amount_overdue'=> (string) $totalOverdue,
                        'oldest_due'    => (string) $oldestDue,
                    ],
                );
            } catch (\Throwable $e) {
                Log::warning('Credit reminder FCM failed', ['account_id' => $this->creditAccountId, 'error' => $e->getMessage()]);
            }
        }

        // SMS placeholder — integrate with your SMS gateway here
        // SmsService::send($user?->phone, $body);

        Log::info('Credit reminder sent', ['account_id' => $this->creditAccountId, 'overdue' => $totalOverdue]);
    }
}
