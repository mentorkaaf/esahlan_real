<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\WithdrawalRequest;
use App\Models\Commission;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminFinanceController extends Controller
{
    public function index()
    {
        $stats = [
            'total_revenue'       => Commission::sum('platform_amount'),
            'pending_withdrawals' => WithdrawalRequest::where('status', 'pending')->sum('amount'),
            'total_payouts'       => WithdrawalRequest::where('status', 'completed')->sum('amount'),
            'wallet_balances'     => Wallet::where('owner_type', 'App\\Models\\User')->sum('balance'),
        ];

        $pendingWithdrawals = WithdrawalRequest::with('owner')
            ->where('status', 'pending')
            ->latest()
            ->paginate(20);

        return view('admin.finance.index', compact('stats', 'pendingWithdrawals'));
    }

    public function approveWithdrawal(Request $request, WithdrawalRequest $withdrawal)
    {
        $request->validate(['transaction_reference' => 'required|string']);

        DB::transaction(function () use ($withdrawal, $request) {
            $wallet = Wallet::getOrCreateFor($withdrawal->owner_type, $withdrawal->owner_id);
            $wallet->debit($withdrawal->amount, 'withdrawal', "Withdrawal approved: {$request->transaction_reference}");

            $withdrawal->update([
                'status'                  => 'completed',
                'transaction_reference'   => $request->transaction_reference,
                'processed_at'            => now(),
                'processed_by'            => auth()->id(),
            ]);
        });

        return back()->with('success', 'Withdrawal approved and processed.');
    }

    public function rejectWithdrawal(Request $request, WithdrawalRequest $withdrawal)
    {
        $withdrawal->update([
            'status'       => 'rejected',
            'admin_note'   => $request->reason,
            'processed_at' => now(),
            'processed_by' => auth()->id(),
        ]);
        return back()->with('success', 'Withdrawal rejected.');
    }

    public function transactions(Request $request)
    {
        $transactions = Transaction::with('wallet.owner')
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest()
            ->paginate(20);

        return view('admin.finance.transactions', compact('transactions'));
    }

    public function commissions(Request $request)
    {
        $commissions = Commission::with(['order', 'vendor'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->vendor_id, fn($q) => $q->where('vendor_id', $request->vendor_id))
            ->latest()
            ->paginate(20);

        return view('admin.finance.commissions', compact('commissions'));
    }
}
