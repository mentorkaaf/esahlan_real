<?php
namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorWalletWebController extends Controller
{
    use HasActiveVendor;

    public function index()
    {
        $vendor = $this->activeVendor();
        $wallet = Wallet::getOrCreateFor('App\\Models\\Vendor', $vendor->id);

        $transactions = DB::table('transactions')
            ->where('wallet_id', $wallet->id)
            ->orderByDesc('created_at')
            ->paginate(30);

        $withdrawals = DB::table('withdrawal_requests')
            ->where('owner_type', 'App\\Models\\Vendor')
            ->where('owner_id', $vendor->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // Earnings summary from orders
        $totalEarning = (float) Order::where('vendor_id', $vendor->id)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('COALESCE(SUM(subtotal - COALESCE(commission, 0)), 0) as total')
            ->value('total');

        $totalCommission = (float) Order::where('vendor_id', $vendor->id)
            ->where('status', '!=', 'cancelled')
            ->sum(DB::raw('COALESCE(commission, 0)'));

        $pendingWithdrawal = (float) DB::table('withdrawal_requests')
            ->where('owner_type', 'App\\Models\\Vendor')
            ->where('owner_id', $vendor->id)
            ->where('status', 'pending')
            ->sum('amount');

        return view('vendor.wallet.index', compact(
            'vendor', 'wallet', 'transactions', 'withdrawals',
            'totalEarning', 'totalCommission', 'pendingWithdrawal'
        ));
    }

    public function requestWithdrawal(Request $request)
    {
        $vendor = $this->activeVendor();
        $wallet = Wallet::getOrCreateFor('App\\Models\\Vendor', $vendor->id);

        $request->validate([
            'amount'         => 'required|numeric|min:1',
            'payment_method' => 'required|in:waafi,evc,others',
            'account_number' => 'required|string',
            'account_name'   => 'required|string',
        ]);

        $amount = (float) $request->amount;
        if ($wallet->balance < $amount) {
            return back()->withErrors(['amount' => 'Insufficient wallet balance.']);
        }

        DB::transaction(function () use ($wallet, $vendor, $amount, $request) {
            $wallet->debit($amount, 'Withdrawal request - pending admin approval');
            DB::table('withdrawal_requests')->insert([
                'wallet_id'      => $wallet->id,
                'owner_type'     => 'App\\Models\\Vendor',
                'owner_id'       => $vendor->id,
                'amount'         => $amount,
                'method'         => $request->payment_method,
                'payment_method' => $request->payment_method,
                'account_number' => $request->account_number,
                'account_name'   => $request->account_name,
                'status'         => 'pending',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        });

        return back()->with('success', 'Withdrawal request submitted.');
    }
}
