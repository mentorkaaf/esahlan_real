<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminExchangeController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('exchange_orders')
            ->join('users', 'users.id', '=', 'exchange_orders.user_id')
            ->select('exchange_orders.*', 'users.name as user_name', 'users.phone as user_phone')
            ->orderByDesc('exchange_orders.created_at');

        if ($request->filled('from_wallet')) {
            $query->where('from_wallet', $request->from_wallet);
        }
        if ($request->filled('to_wallet')) {
            $query->where('to_wallet', $request->to_wallet);
        }
        if ($request->filled('status')) {
            $query->where('exchange_orders.status', $request->status);
        }
        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('reference', 'like', $s)
                  ->orWhere('recipient_phone', 'like', $s)
                  ->orWhere('users.name', 'like', $s);
            });
        }

        $orders = $query->paginate(20)->withQueryString();

        $stats = [
            'total'     => DB::table('exchange_orders')->count(),
            'today'     => DB::table('exchange_orders')->whereDate('created_at', today())->count(),
            'volume'    => DB::table('exchange_orders')->where('status', 'completed')->sum('sent_amount'),
            'fees'      => DB::table('exchange_orders')->where('status', 'completed')->sum('fee_amount'),
        ];

        return view('admin.exchange.index', compact('orders', 'stats'));
    }

    public function show(int $id)
    {
        $order = DB::table('exchange_orders')
            ->join('users', 'users.id', '=', 'exchange_orders.user_id')
            ->select('exchange_orders.*', 'users.name as user_name', 'users.phone as user_phone', 'users.email as user_email')
            ->where('exchange_orders.id', $id)
            ->first();

        abort_if(!$order, 404);

        return view('admin.exchange.show', compact('order'));
    }

    public function destroy(int $id)
    {
        \Illuminate\Support\Facades\DB::table('exchange_orders')->where('id', $id)->delete();
        return back()->with('success', 'Exchange order deleted.');
    }
}
