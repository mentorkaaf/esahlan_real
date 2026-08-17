<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminExchangeController extends Controller
{
    // ── Orders Index ──────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = DB::table('exchange_orders')
            ->join('users', 'users.id', '=', 'exchange_orders.user_id')
            ->select(
                'exchange_orders.*',
                'users.name as user_name',
                'users.phone as user_phone',
                'users.email as user_email'
            )
            ->orderByDesc('exchange_orders.created_at');

        if ($request->filled('from_wallet')) $query->where('from_wallet', strtoupper($request->from_wallet));
        if ($request->filled('to_wallet'))   $query->where('to_wallet',   strtoupper($request->to_wallet));
        if ($request->filled('status'))      $query->where('exchange_orders.status', $request->status);
        if ($request->filled('date_from'))   $query->whereDate('exchange_orders.created_at', '>=', $request->date_from);
        if ($request->filled('date_to'))     $query->whereDate('exchange_orders.created_at', '<=', $request->date_to);

        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('reference', 'like', $s)
                  ->orWhere('recipient_phone', 'like', $s)
                  ->orWhere('users.name', 'like', $s)
                  ->orWhere('users.phone', 'like', $s);
            });
        }

        // Fraud flag: large amount filter
        if ($request->filled('fraud_only')) {
            $query->where(function ($q) {
                $q->where('sent_amount', '>=', 500) // large single transfer
                  ->orWhereRaw('(SELECT COUNT(*) FROM exchange_orders e2 WHERE e2.user_id = exchange_orders.user_id AND e2.created_at >= NOW() - INTERVAL 1 HOUR) >= 5');
            });
        }

        $orders = $query->paginate(25)->withQueryString();

        // ── Stats ─────────────────────────────────────────────────────────────
        $stats = [
            'total'         => DB::table('exchange_orders')->count(),
            'today'         => DB::table('exchange_orders')->whereDate('created_at', today())->count(),
            'pending'       => DB::table('exchange_orders')->where('status', 'pending')->count(),
            'completed'     => DB::table('exchange_orders')->where('status', 'completed')->count(),
            'volume'        => DB::table('exchange_orders')->where('status', 'completed')->sum('sent_amount'),
            'volume_today'  => DB::table('exchange_orders')->where('status', 'completed')->whereDate('created_at', today())->sum('sent_amount'),
            'fees'          => DB::table('exchange_orders')->where('status', 'completed')->sum('fee_amount'),
            'fees_today'    => DB::table('exchange_orders')->where('status', 'completed')->whereDate('created_at', today())->sum('fee_amount'),
            // Fraud indicators
            'large_orders'  => DB::table('exchange_orders')->where('sent_amount', '>=', 500)->whereDate('created_at', today())->count(),
            'failed'        => DB::table('exchange_orders')->where('status', 'failed')->whereDate('created_at', today())->count(),
        ];

        // Volume by wallet pair (top 6)
        $pairVolume = DB::table('exchange_orders')
            ->selectRaw('from_wallet, to_wallet, COUNT(*) as cnt, SUM(sent_amount) as vol')
            ->where('status', 'completed')
            ->groupBy('from_wallet', 'to_wallet')
            ->orderByDesc('cnt')
            ->limit(6)
            ->get();

        // 7-day chart
        $chart = collect(range(6, 0))->map(function ($i) {
            $date = now()->subDays($i)->toDateString();
            return [
                'date'   => $date,
                'count'  => DB::table('exchange_orders')->whereDate('created_at', $date)->count(),
                'volume' => DB::table('exchange_orders')->where('status', 'completed')->whereDate('created_at', $date)->sum('sent_amount'),
            ];
        });

        return view('admin.exchange.index', compact('orders', 'stats', 'pairVolume', 'chart'));
    }

    // ── Order Detail ──────────────────────────────────────────────────────────
    public function show(int $id)
    {
        $order = DB::table('exchange_orders')
            ->join('users', 'users.id', '=', 'exchange_orders.user_id')
            ->select('exchange_orders.*', 'users.name as user_name', 'users.phone as user_phone', 'users.email as user_email')
            ->where('exchange_orders.id', $id)
            ->first();

        abort_if(!$order, 404);

        // User's full history
        $userHistory = DB::table('exchange_orders')
            ->where('user_id', $order->user_id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // Fraud indicators for this user
        $fraud = [
            'orders_today'  => DB::table('exchange_orders')->where('user_id', $order->user_id)->whereDate('created_at', today())->count(),
            'orders_week'   => DB::table('exchange_orders')->where('user_id', $order->user_id)->where('created_at', '>=', now()->subDays(7))->count(),
            'total_volume'  => DB::table('exchange_orders')->where('user_id', $order->user_id)->where('status', 'completed')->sum('sent_amount'),
            'unique_phones' => DB::table('exchange_orders')->where('user_id', $order->user_id)->distinct('recipient_phone')->count('recipient_phone'),
        ];

        return view('admin.exchange.show', compact('order', 'userHistory', 'fraud'));
    }

    // ── Update Order Status ────────────────────────────────────────────────────
    public function updateStatus(Request $request, int $id)
    {
        $request->validate(['status' => 'required|in:pending,processing,completed,failed']);
        DB::table('exchange_orders')->where('id', $id)->update([
            'status'     => $request->status,
            'updated_at' => now(),
        ]);
        return back()->with('success', 'Order status updated to ' . ucfirst($request->status));
    }

    // ── Users with Exchange Accounts ──────────────────────────────────────────
    public function users(Request $request)
    {
        $query = DB::table('users')
            ->join('exchange_accounts', 'users.id', '=', 'exchange_accounts.user_id')
            ->select('users.id', 'users.name', 'users.phone', 'users.email', 'users.created_at as joined')
            ->selectRaw('COUNT(exchange_accounts.id) as account_count')
            ->selectRaw('(SELECT COUNT(*) FROM exchange_orders WHERE exchange_orders.user_id = users.id) as order_count')
            ->selectRaw('(SELECT SUM(sent_amount) FROM exchange_orders WHERE exchange_orders.user_id = users.id AND status = "completed") as total_volume')
            ->groupBy('users.id', 'users.name', 'users.phone', 'users.email', 'users.created_at')
            ->orderByDesc('order_count');

        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('users.name', 'like', $s)->orWhere('users.phone', 'like', $s);
            });
        }

        $users = $query->paginate(20)->withQueryString();

        return view('admin.exchange.users', compact('users'));
    }

    // ── User Accounts Detail ──────────────────────────────────────────────────
    public function userAccounts(int $userId)
    {
        $user = DB::table('users')->where('id', $userId)->first();
        abort_if(!$user, 404);

        $accounts = DB::table('exchange_accounts')->where('user_id', $userId)->orderBy('wallet_type')->get();
        $orders   = DB::table('exchange_orders')->where('user_id', $userId)->orderByDesc('created_at')->limit(50)->get();

        $fraud = [
            'orders_today'  => DB::table('exchange_orders')->where('user_id', $userId)->whereDate('created_at', today())->count(),
            'orders_week'   => DB::table('exchange_orders')->where('user_id', $userId)->where('created_at', '>=', now()->subDays(7))->count(),
            'total_volume'  => DB::table('exchange_orders')->where('user_id', $userId)->where('status', 'completed')->sum('sent_amount'),
            'unique_phones' => DB::table('exchange_orders')->where('user_id', $userId)->distinct('recipient_phone')->count('recipient_phone'),
        ];

        return view('admin.exchange.user_accounts', compact('user', 'accounts', 'orders', 'fraud'));
    }

    // ── Delete Account ────────────────────────────────────────────────────────
    public function deleteAccount(int $id)
    {
        DB::table('exchange_accounts')->where('id', $id)->delete();
        return back()->with('success', 'Account removed.');
    }

    // ── Delete Order ──────────────────────────────────────────────────────────
    public function destroy(int $id)
    {
        DB::table('exchange_orders')->where('id', $id)->delete();
        return back()->with('success', 'Exchange order deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));
        if (empty($ids)) return back()->with('error', 'No orders selected.');
        DB::table('exchange_orders')->whereIn('id', $ids)->delete();
        return back()->with('success', count($ids) . ' order(s) deleted.');
    }
}
