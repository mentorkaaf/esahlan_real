<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\User;
use App\Services\FcmService;
use App\Helpers\AppSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;

class AdminWalletController extends Controller
{
    // ─── Dashboard ────────────────────────────────────────────────────────────

    public function index()
    {
        $users = DB::table('users')
            ->leftJoin('wallets', function ($j) {
                $j->on('wallets.owner_id', '=', 'users.id')
                  ->where('wallets.owner_type', 'App\\Models\\User');
            })
            ->whereNull('users.deleted_at')
            ->select('users.id', 'users.name', 'users.email', 'users.phone',
                     'wallets.id as wallet_id', 'wallets.balance', 'wallets.total_earned',
                     'wallets.total_withdrawn', 'wallets.is_frozen', 'wallets.frozen_reason')
            ->orderByDesc('wallets.balance')
            ->paginate(30);

        $stats = $this->_dashboardStats();

        return view('admin.wallet.index', compact('users', 'stats'));
    }

    private function _dashboardStats(): array
    {
        $today     = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        $week      = now()->subDays(7)->startOfDay();
        $month     = now()->startOfMonth();

        $todayCred  = DB::table('transactions')->whereDate('created_at', today())->where('type','credit')->sum('amount');
        $todayDeb   = DB::table('transactions')->whereDate('created_at', today())->where('type','debit')->sum('amount');
        $todayVol   = $todayCred;  // volume = money IN only, debit would double-count transfers
        $monthVol   = DB::table('transactions')->where('created_at', '>=', $month)->where('type','credit')->sum('amount');

        // 7-day daily volumes for chart
        $dailyData = DB::table('transactions')
            ->where('created_at', '>=', $week)
            ->selectRaw("DATE(created_at) as date, SUM(CASE WHEN type='credit' THEN amount ELSE 0 END) as credit, SUM(CASE WHEN type='debit' THEN amount ELSE 0 END) as debit")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Transaction type breakdown (by payment_method)
        $methodBreakdown = DB::table('transactions')
            ->where('created_at', '>=', $month)
            ->selectRaw("payment_method, count(*) as cnt, sum(amount) as vol")
            ->groupBy('payment_method')
            ->orderByDesc('vol')
            ->limit(8)
            ->get();

        // Top users by balance
        $topUsers = DB::table('wallets')
            ->join('users', function ($j) {
                $j->on('users.id','=','wallets.owner_id')->where('wallets.owner_type','App\\Models\\User');
            })
            ->whereNull('users.deleted_at')
            ->where('wallets.balance', '>', 0)
            ->select('users.id','users.name','users.phone','wallets.balance','wallets.total_earned','wallets.total_withdrawn','wallets.is_frozen')
            ->orderByDesc('wallets.balance')
            ->limit(10)
            ->get();

        // Pending withdrawal count + amount
        $pendingWd = DB::table('withdrawal_requests')->where('status','pending')
            ->selectRaw('count(*) as cnt, sum(amount) as total')->first();

        // Total topups this month
        $monthlyTopups = DB::table('payment_transactions')
            ->where('created_at','>=',$month)->where('status','success')
            ->sum('amount');

        // Active/frozen wallet counts
        $frozenCount = DB::table('wallets')->where('is_frozen', true)->count();

        // P2P transfers this month
        $p2pCount = DB::table('transactions')
            ->where('created_at','>=',$month)
            ->where('note','like','Transfer%')
            ->count();

        return [
            'total_balance'         => (float) DB::table('wallets')->where('owner_type','App\\Models\\User')->sum('balance'),
            'total_topups'          => (float) DB::table('payment_transactions')->where('status','success')->sum('amount'),
            'pending_withdrawals'   => (float) ($pendingWd->total ?? 0),
            'pending_withdrawal_cnt'=> (int)   ($pendingWd->cnt  ?? 0),
            'user_count'            => (int)   DB::table('wallets')->where('owner_type','App\\Models\\User')->count(),
            'frozen_count'          => (int)   $frozenCount,
            'today_volume'          => (float) $todayVol,
            'today_credit'          => (float) $todayCred,
            'today_debit'           => (float) $todayDeb,
            'month_volume'          => (float) $monthVol,
            'month_topups'          => (float) $monthlyTopups,
            'p2p_count'             => (int)   $p2pCount,
            'total_transactions'    => (int)   DB::table('transactions')->count(),
            'daily_chart'           => $dailyData,
            'method_breakdown'      => $methodBreakdown,
            'top_users'             => $topUsers,
        ];
    }

    // ─── Transactions ─────────────────────────────────────────────────────────

    public function transactions(Request $request)
    {
        $q = DB::table('transactions')
            ->join('wallets', 'wallets.id', '=', 'transactions.wallet_id')
            ->join('users', function ($j) {
                $j->on('users.id', '=', 'wallets.owner_id')
                  ->where('wallets.owner_type', 'App\\Models\\User');
            })
            ->select('transactions.*', 'users.name as user_name', 'users.email as user_email',
                     'users.phone as user_phone', 'wallets.balance as current_balance')
            ->orderByDesc('transactions.created_at');

        if ($request->user_id)   $q->where('users.id', $request->user_id);
        if ($request->type)      $q->where('transactions.type', $request->type);
        if ($request->method)    $q->where('transactions.payment_method', $request->method);
        if ($request->date_from) $q->whereDate('transactions.created_at', '>=', $request->date_from);
        if ($request->date_to)   $q->whereDate('transactions.created_at', '<=', $request->date_to);
        if ($request->search) {
            $s = $request->search;
            $q->where(function ($qq) use ($s) {
                $qq->where('users.name', 'like', "%$s%")
                   ->orWhere('users.phone', 'like', "%$s%")
                   ->orWhere('transactions.note', 'like', "%$s%")
                   ->orWhere('transactions.payment_reference', 'like', "%$s%");
            });
        }

        // Summary stats — rebuild as a fresh aggregate query to avoid MySQL only_full_group_by
        $statsBase = DB::table('transactions')
            ->join('wallets', 'wallets.id', '=', 'transactions.wallet_id')
            ->join('users', function ($j) {
                $j->on('users.id', '=', 'wallets.owner_id')
                  ->where('wallets.owner_type', 'App\\Models\\User');
            });

        if ($request->user_id)   $statsBase->where('users.id', $request->user_id);
        if ($request->type)      $statsBase->where('transactions.type', $request->type);
        if ($request->method)    $statsBase->where('transactions.payment_method', $request->method);
        if ($request->date_from) $statsBase->whereDate('transactions.created_at', '>=', $request->date_from);
        if ($request->date_to)   $statsBase->whereDate('transactions.created_at', '<=', $request->date_to);
        if ($request->search) {
            $s = $request->search;
            $statsBase->where(function ($qq) use ($s) {
                $qq->where('users.name', 'like', "%$s%")
                   ->orWhere('users.phone', 'like', "%$s%")
                   ->orWhere('transactions.note', 'like', "%$s%")
                   ->orWhere('transactions.payment_reference', 'like', "%$s%");
            });
        }

        $summary = $statsBase->selectRaw("
            count(*) as total_count,
            sum(CASE WHEN transactions.type='credit' THEN transactions.amount ELSE 0 END) as total_credit,
            sum(CASE WHEN transactions.type='debit' THEN transactions.amount ELSE 0 END) as total_debit
        ")->first();

        if ($request->export === 'csv') {
            return $this->_exportTransactionsCsv($q);
        }

        $filterUser = $request->user_id
            ? DB::table('users')->where('id', $request->user_id)->first()
            : null;

        $transactions = $q->paginate(50)->withQueryString();
        $methods = DB::table('transactions')->distinct()->pluck('payment_method')->filter()->sort()->values();

        return view('admin.wallet.transactions', compact('transactions', 'filterUser', 'summary', 'methods'));
    }

    private function _exportTransactionsCsv($query)
    {
        $rows = $query->limit(10000)->get();
        $csv  = "ID,User,Phone,Type,Amount,Balance After,Note,Method,Reference,Date\n";
        foreach ($rows as $r) {
            $csv .= implode(',', [
                $r->id,
                '"' . str_replace('"', '""', $r->user_name) . '"',
                $r->user_phone ?? '',
                $r->type,
                $r->amount,
                $r->balance_after,
                '"' . str_replace('"', '""', $r->note ?? '') . '"',
                $r->payment_method ?? '',
                $r->payment_reference ?? '',
                $r->created_at,
            ]) . "\n";
        }
        return Response::make($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="epay-transactions-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }

    // ─── Manual Credit ────────────────────────────────────────────────────────

    public function creditUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount'  => 'required|numeric|min:0.01|max:100000',
            'note'    => 'required|string|max:300',
        ]);

        $wallet = Wallet::getOrCreateFor('App\\Models\\User', $request->user_id);

        if ($wallet->is_frozen) {
            return back()->with('error', 'Wallet is frozen. Unfreeze it first.');
        }

        $wallet->credit((float) $request->amount, 'Admin credit: ' . $request->note, null, null, 'admin');

        try {
            $u = User::find($request->user_id);
            if ($u?->fcm_token) FcmService::sendWalletCredit($u->fcm_token, (float) $request->amount, (float) $wallet->fresh()->balance);
        } catch (\Throwable $e) {}

        return back()->with('success', 'Credit of $' . number_format($request->amount, 2) . ' added successfully.');
    }

    // ─── Manual Debit ─────────────────────────────────────────────────────────

    public function debitUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount'  => 'required|numeric|min:0.01|max:100000',
            'note'    => 'required|string|max:300',
        ]);

        $wallet = Wallet::where('owner_type', 'App\\Models\\User')->where('owner_id', $request->user_id)->first();
        if (!$wallet || $wallet->balance < (float) $request->amount) {
            return back()->with('error', 'Insufficient balance or wallet not found.');
        }

        $wallet->debit((float) $request->amount, 'Admin debit: ' . $request->note, null, null);

        return back()->with('success', 'Debit of $' . number_format($request->amount, 2) . ' applied successfully.');
    }

    // ─── Freeze / Unfreeze ────────────────────────────────────────────────────

    public function freezeWallet(Request $request, $userId)
    {
        $request->validate(['reason' => 'required|string|max:500']);
        $wallet = Wallet::where('owner_type', 'App\\Models\\User')->where('owner_id', $userId)->first();
        if (!$wallet) return back()->with('error', 'Wallet not found.');
        $wallet->update([
            'is_frozen'    => true,
            'frozen_reason'=> $request->reason,
            'frozen_by'    => auth()->id(),
            'frozen_at'    => now(),
        ]);
        return back()->with('success', 'Wallet frozen successfully.');
    }

    public function unfreezeWallet($userId)
    {
        $wallet = Wallet::where('owner_type', 'App\\Models\\User')->where('owner_id', $userId)->first();
        if (!$wallet) return back()->with('error', 'Wallet not found.');
        $wallet->update(['is_frozen' => false, 'frozen_reason' => null, 'frozen_by' => null, 'frozen_at' => null]);
        return back()->with('success', 'Wallet unfrozen successfully.');
    }

    // ─── P2P Admin Transfer ───────────────────────────────────────────────────

    public function transferBetweenUsers(Request $request)
    {
        $request->validate([
            'from_user_id' => 'required|exists:users,id|different:to_user_id',
            'to_user_id'   => 'required|exists:users,id',
            'amount'       => 'required|numeric|min:0.01|max:100000',
            'note'         => 'required|string|max:300',
        ]);

        $fromWallet = Wallet::where('owner_type','App\\Models\\User')->where('owner_id',$request->from_user_id)->first();
        if (!$fromWallet || $fromWallet->balance < (float) $request->amount) {
            return back()->with('error', 'Source user has insufficient balance.');
        }

        $toWallet = Wallet::getOrCreateFor('App\\Models\\User', $request->to_user_id);
        $note     = $request->note;

        DB::transaction(function () use ($fromWallet, $toWallet, $request, $note) {
            $amt = (float) $request->amount;
            $fromUser = User::find($request->from_user_id);
            $toUser   = User::find($request->to_user_id);
            $fromWallet->debit($amt, 'Transfer to ' . ($toUser->name ?? 'user') . ': ' . $note);
            $toWallet->credit($amt, 'Transfer from ' . ($fromUser->name ?? 'user') . ': ' . $note);
        });

        return back()->with('success', 'Transfer of $' . number_format($request->amount, 2) . ' completed.');
    }

    // ─── Withdrawals ──────────────────────────────────────────────────────────

    public function withdrawals(Request $request)
    {
        $q = DB::table('withdrawal_requests')
            ->where('withdrawal_requests.owner_type', 'App\\Models\\User')
            ->join('users', 'users.id', '=', 'withdrawal_requests.owner_id')
            ->leftJoin('users as reviewer', 'reviewer.id', '=', 'withdrawal_requests.reviewed_by')
            ->select('withdrawal_requests.*', 'users.name as user_name', 'users.phone as user_phone',
                     'users.email as user_email', 'reviewer.name as reviewer_name')
            ->orderByDesc('withdrawal_requests.created_at');

        if ($request->status && $request->status !== 'all') $q->where('withdrawal_requests.status', $request->status);
        if ($request->date_from) $q->whereDate('withdrawal_requests.created_at', '>=', $request->date_from);
        if ($request->date_to)   $q->whereDate('withdrawal_requests.created_at', '<=', $request->date_to);
        if ($request->search) {
            $s = $request->search;
            $q->where(function ($qq) use ($s) {
                $qq->where('users.name','like',"%$s%")
                   ->orWhere('users.phone','like',"%$s%")
                   ->orWhere('withdrawal_requests.account_number','like',"%$s%")
                   ->orWhere('withdrawal_requests.transaction_reference','like',"%$s%");
            });
        }

        if ($request->export === 'csv') {
            return $this->_exportWithdrawalsCsv($q);
        }

        $summary = DB::table('withdrawal_requests')->where('owner_type','App\\Models\\User')
            ->selectRaw("
                sum(CASE WHEN status='pending' THEN amount ELSE 0 END) as pending_amount,
                count(CASE WHEN status='pending' THEN 1 END) as pending_count,
                sum(CASE WHEN status='approved' THEN amount ELSE 0 END) as approved_amount,
                sum(CASE WHEN status='processed' THEN amount ELSE 0 END) as processed_amount,
                count(*) as total_count, sum(amount) as total_amount
            ")->first();

        $requests = $q->paginate(30)->withQueryString();
        return view('admin.wallet.withdrawals', compact('requests', 'summary'));
    }

    public function approveWithdrawal(Request $request, $id)
    {
        $wr = DB::table('withdrawal_requests')->find($id);
        if (!$wr || $wr->status !== 'pending') return back()->with('error', 'Invalid request.');

        DB::table('withdrawal_requests')->where('id', $id)->update([
            'status'      => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'admin_note'  => $request->admin_note,
            'updated_at'  => now(),
        ]);

        try {
            $u = $wr ? User::find($wr->owner_id) : null;
            if ($u?->fcm_token) FcmService::sendWithdrawalApproved($u->fcm_token, (float) ($wr->amount ?? 0));
        } catch (\Throwable $e) {}

        return back()->with('success', 'Withdrawal approved.');
    }

    public function rejectWithdrawal(Request $request, $id)
    {
        $wr = DB::table('withdrawal_requests')->find($id);
        if (!$wr || $wr->status !== 'pending') return back()->with('error', 'Invalid request.');

        DB::transaction(function () use ($wr, $id, $request) {
            // Use wallet_id directly so vendor/user wallets are both handled correctly
            $wallet = $wr->wallet_id
                ? Wallet::find($wr->wallet_id)
                : Wallet::getOrCreateFor($wr->owner_type ?? 'App\\Models\\User', $wr->owner_id);
            $wallet->credit((float) $wr->amount, 'Withdrawal rejected - refunded', null, null, 'refund');
            DB::table('withdrawal_requests')->where('id', $id)->update([
                'status'      => 'rejected',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'admin_note'  => $request->admin_note,
                'updated_at'  => now(),
            ]);
        });

        try {
            $u = User::find($wr->owner_id);
            if ($u?->fcm_token) FcmService::sendWithdrawalRejected($u->fcm_token, (float) $wr->amount);
        } catch (\Throwable $e) {}

        return back()->with('success', 'Withdrawal rejected and amount refunded.');
    }

    public function processWithdrawal(Request $request, $id)
    {
        $request->validate(['transaction_reference' => 'required|string|max:200']);
        $wr = DB::table('withdrawal_requests')->find($id);
        if (!$wr || $wr->status !== 'approved') return back()->with('error', 'Only approved withdrawals can be marked as processed.');

        DB::table('withdrawal_requests')->where('id', $id)->update([
            'status'                => 'processed',
            'processed_by'          => auth()->id(),
            'processed_at'          => now(),
            'transaction_reference' => $request->transaction_reference,
            'admin_note'            => $request->admin_note,
            'updated_at'            => now(),
        ]);

        return back()->with('success', 'Withdrawal marked as processed.');
    }

    public function bulkApproveWithdrawals(Request $request)
    {
        $ids = explode(',', $request->ids ?? '');
        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) return back()->with('error', 'No items selected.');

        $count = DB::table('withdrawal_requests')
            ->whereIn('id', $ids)
            ->where('status', 'pending')
            ->update(['status' => 'approved', 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'updated_at' => now()]);

        return back()->with('success', "$count withdrawal(s) approved.");
    }

    private function _exportWithdrawalsCsv($query)
    {
        $rows = $query->limit(5000)->get();
        $csv  = "ID,User,Phone,Amount,Method,Account Number,Account Name,Status,Reference,Admin Note,Date\n";
        foreach ($rows as $r) {
            $csv .= implode(',', [
                $r->id,
                '"' . str_replace('"','""',$r->user_name) . '"',
                $r->user_phone ?? '',
                $r->amount,
                $r->method ?? '',
                $r->account_number ?? '',
                '"' . str_replace('"','""',$r->account_name ?? '') . '"',
                $r->status,
                $r->transaction_reference ?? '',
                '"' . str_replace('"','""',$r->admin_note ?? '') . '"',
                $r->created_at,
            ]) . "\n";
        }
        return Response::make($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="withdrawals-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }

    // ─── User Financial Profile ───────────────────────────────────────────────

    public function userDetail($userId)
    {
        $user   = User::findOrFail($userId);
        $wallet = Wallet::where('owner_type','App\\Models\\User')->where('owner_id',$userId)->first();

        $transactions = DB::table('transactions')
            ->where('wallet_id', $wallet?->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $withdrawals = DB::table('withdrawal_requests')
            ->where('owner_id', $userId)->where('owner_type','App\\Models\\User')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // 30-day daily balance trend
        $trend = DB::table('transactions')
            ->where('wallet_id', $wallet?->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw("DATE(created_at) as date, MAX(balance_after) as eod_balance")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $monthStats = DB::table('transactions')
            ->where('wallet_id', $wallet?->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw("
                sum(CASE WHEN type='credit' THEN amount ELSE 0 END) as month_credit,
                sum(CASE WHEN type='debit' THEN amount ELSE 0 END) as month_debit,
                count(*) as month_count
            ")->first();

        return view('admin.wallet.user-detail', compact('user','wallet','transactions','withdrawals','trend','monthStats'));
    }

    // ─── Wallet Resets ────────────────────────────────────────────────────────

    public function resetWallet(Request $request, $userId)
    {
        $wallet = Wallet::where('owner_type', 'App\\Models\\User')->where('owner_id', $userId)->first();
        if (!$wallet || $wallet->balance <= 0) {
            return back()->with('error', 'Wallet not found or already zero.');
        }
        $wallet->debit((float) $wallet->balance, 'Admin wallet reset', null, null);
        return back()->with('success', 'Wallet reset to $0.00.');
    }

    public function bulkResetWallets(Request $request)
    {
        DB::transaction(function () {
            // Record audit debit transactions for non-zero balances before zeroing
            $wallets = Wallet::where('owner_type', 'App\\Models\\User')->where('balance', '>', 0)->get();
            foreach ($wallets as $wallet) {
                $wallet->debit((float) $wallet->balance, 'Admin full ePay reset', null, null);
            }

            // Zero out lifetime stats so dashboard starts completely fresh
            DB::table('wallets')
                ->where('owner_type', 'App\\Models\\User')
                ->update([
                    'total_earned'    => 0,
                    'total_withdrawn' => 0,
                    'updated_at'      => now(),
                ]);
        });

        $count = DB::table('wallets')->where('owner_type', 'App\\Models\\User')->count();
        return back()->with('success', "$count wallet(s) fully reset — balance, total earned, and total withdrawn set to \$0.00.");
    }

    public function nukeAll(Request $request)
    {
        DB::transaction(function () {
            // Delete all transaction history
            DB::table('transactions')->delete();

            // Reset all wallet stats and balances to zero
            DB::table('wallets')
                ->where('owner_type', 'App\\Models\\User')
                ->update([
                    'balance'         => 0,
                    'total_earned'    => 0,
                    'total_withdrawn' => 0,
                    'updated_at'      => now(),
                ]);
        });

        return redirect()->route('admin.wallet.transactions')
            ->with('success', 'ePay fully reset — all transactions deleted and all wallet balances zeroed.');
    }

    public function resetUserPin(Request $request, $userId)
    {
        $request->validate(['pin' => 'required|digits:4']);
        $user = User::findOrFail($userId);
        $user->update(['wallet_pin' => Hash::make($request->pin)]);
        return back()->with('success', "Wallet PIN reset for {$user->name}.");
    }

    // ─── Settings ─────────────────────────────────────────────────────────────

    public function settings()
    {
        $settings = [
            'waafi_merchant_uid'     => AppSettings::get('waafi_merchant_uid', ''),
            'waafi_api_user_id'      => AppSettings::get('waafi_api_user_id', ''),
            'waafi_api_key'          => AppSettings::get('waafi_api_key', ''),
            'waafi_api_url'          => AppSettings::get('waafi_api_url', 'https://api.waafipay.net/asm'),
            'waafi_description'      => AppSettings::get('waafi_description', 'eSahlan Payment'),
            'withdrawal_min_amount'  => AppSettings::get('withdrawal_min_amount', '1'),
            'withdrawal_max_amount'  => AppSettings::get('withdrawal_max_amount', '10000'),
            'withdrawal_fee_pct'     => AppSettings::get('withdrawal_fee_pct', '0'),
            'withdrawal_fee_fixed'   => AppSettings::get('withdrawal_fee_fixed', '0'),
            'wallet_daily_limit'     => AppSettings::get('wallet_daily_limit', '0'),
            'wallet_enabled'         => AppSettings::get('wallet_enabled', true),
        ];
        return view('admin.wallet.settings', compact('settings'));
    }

    public function saveSettings(Request $request)
    {
        $fields = [
            'waafi_merchant_uid','waafi_api_user_id','waafi_api_key','waafi_api_url','waafi_description',
            'withdrawal_min_amount','withdrawal_max_amount','withdrawal_fee_pct','withdrawal_fee_fixed',
            'wallet_daily_limit','wallet_enabled',
        ];
        foreach ($fields as $key) {
            if ($request->has($key)) AppSettings::set($key, $request->input($key));
        }
        return back()->with('success', 'Settings saved successfully.');
    }
}
