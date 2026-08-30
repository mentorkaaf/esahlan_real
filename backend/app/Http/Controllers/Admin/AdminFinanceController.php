<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\WithdrawalRequest;
use App\Models\Commission;
use App\Models\Wallet;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminFinanceController extends Controller
{
    public function index()
    {
        // ── GMV & Revenue ─────────────────────────────────────────────
        $gmvTotal          = (float) Order::where('status', 'delivered')->sum('total_amount');
        $deliveryFeesTotal = (float) Order::where('status', 'delivered')->sum('delivery_fee');
        $bonusPaidTotal    = (float) Order::where('status', 'delivered')->sum('bonus_amount');
        $discountsTotal    = (float) Order::where('status', 'delivered')
                                ->selectRaw('SUM(COALESCE(discount_amount,0) + COALESCE(coupon_discount,0)) as total')
                                ->value('total');
        $commissionEarned  = (float) Commission::sum('commission_amount')
                           + (float) Commission::sum('delivery_fee_commission');

        // ── Withdrawals ────────────────────────────────────────────────
        $pendingAmt   = (float) WithdrawalRequest::where('status', 'pending')->sum('amount');
        $pendingCount = (int)   WithdrawalRequest::where('status', 'pending')->count();
        $processedAmt = (float) WithdrawalRequest::whereIn('status', ['approved','processed','completed'])->sum('amount');
        $rejectedAmt  = (float) WithdrawalRequest::where('status', 'rejected')->sum('amount');
        $netRevenue   = round($commissionEarned - $processedAmt, 2);

        // ── Wallet Balances by owner type ──────────────────────────────
        $walletByType = Wallet::selectRaw('owner_type, SUM(balance) as total, COUNT(*) as cnt')
            ->groupBy('owner_type')
            ->get()
            ->keyBy(fn($r) => class_basename($r->owner_type));
        $walletTotal = (float) Wallet::sum('balance');

        // ── Revenue by Module (this month) ────────────────────────────
        $revenueByModule = Order::where('status', 'delivered')
            ->selectRaw('
                module_slug,
                COUNT(*) as orders,
                SUM(total_amount)   as gmv,
                SUM(delivery_fee)   as delivery_fees,
                SUM(COALESCE(bonus_amount,0)) as bonus,
                SUM(commission)     as commission,
                SUM(COALESCE(discount_amount,0)+COALESCE(coupon_discount,0)) as discounts
            ')
            ->groupBy('module_slug')
            ->orderByDesc('gmv')
            ->get();

        // ── Pending Withdrawals ────────────────────────────────────────
        $pendingWithdrawals = WithdrawalRequest::with('owner')
            ->where('status', 'pending')
            ->latest()
            ->paginate(20);

        // ── Recent processed withdrawals (last 10) ─────────────────────
        $recentProcessed = WithdrawalRequest::with('owner')
            ->whereIn('status', ['processed','completed','rejected'])
            ->latest('processed_at')
            ->limit(10)
            ->get();

        $stats = compact(
            'gmvTotal','deliveryFeesTotal','bonusPaidTotal','discountsTotal',
            'commissionEarned','pendingAmt','pendingCount','processedAmt',
            'rejectedAmt','netRevenue','walletByType','walletTotal'
        );

        return view('admin.finance.index', compact(
            'stats','revenueByModule','pendingWithdrawals','recentProcessed'
        ));
    }

    public function approveWithdrawal(Request $request, WithdrawalRequest $withdrawal)
    {
        $request->validate(['transaction_reference' => 'required|string']);
        $withdrawal->update([
            'status'                => 'processed',
            'transaction_reference' => $request->transaction_reference,
            'processed_at'          => now(),
            'processed_by'          => auth()->id(),
        ]);
        return back()->with('success', 'Withdrawal approved and processed.');
    }

    public function rejectWithdrawal(Request $request, WithdrawalRequest $withdrawal)
    {
        if ($withdrawal->status !== 'pending') {
            return back()->with('error', 'Only pending requests can be rejected.');
        }
        DB::transaction(function () use ($withdrawal, $request) {
            $wallet = Wallet::find($withdrawal->wallet_id);
            if ($wallet) {
                $wallet->credit((float) $withdrawal->amount, 'Withdrawal rejected — refunded by admin', null, null, 'refund');
            }
            $withdrawal->update([
                'status'       => 'rejected',
                'admin_note'   => $request->reason,
                'processed_at' => now(),
                'processed_by' => auth()->id(),
            ]);
        });
        return back()->with('success', 'Withdrawal rejected and amount refunded to wallet.');
    }

    public function transactions(Request $request)
    {
        $transactions = Transaction::with('wallet.owner')
            ->when($request->type,      fn($q) => $q->where('type', $request->type))
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest()
            ->paginate(20);
        return view('admin.finance.transactions', compact('transactions'));
    }

    public function resetTransactions(Request $request)
    {
        $action = $request->input('action');
        DB::transaction(function () use ($action) {
            Wallet::query()->update(['balance' => 0]);
            if ($action === 'delete') {
                Transaction::query()->delete();
            } else {
                Transaction::query()->update(['amount' => 0,'balance_before' => 0,'balance_after' => 0,'note' => '[RESET BY ADMIN]']);
            }
        });
        return redirect()->route('admin.finance.transactions')
            ->with('success', 'All transactions ' . ($action === 'delete' ? 'deleted' : 'reset') . ' and wallet balances zeroed.');
    }

    public function commissions(Request $request)
    {
        $commissions = Commission::with(['order', 'vendor'])
            ->when($request->status,    fn($q) => $q->where('status', $request->status))
            ->when($request->vendor_id, fn($q) => $q->where('vendor_id', $request->vendor_id))
            ->latest()
            ->paginate(20);
        return view('admin.finance.commissions', compact('commissions'));
    }
}
