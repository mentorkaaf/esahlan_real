<?php
namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorWalletWebController extends Controller
{
    public function index()
    {
        $vendor = auth()->user()->vendor;
        $wallet = Wallet::where('owner_type', 'App\\Models\\Vendor')->where('owner_id', $vendor->id)->first();

        $transactions = [];
        $total        = 0;
        if ($wallet) {
            $txns         = DB::table('transactions')->where('wallet_id', $wallet->id)->orderByDesc('created_at')->paginate(30);
            $transactions = $txns;
            $total        = $txns->total();
        }

        $withdrawals = DB::table('withdrawal_requests')
            ->where('owner_type', 'App\\Models\\Vendor')
            ->where('owner_id', $vendor->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('vendor.wallet.index', compact('vendor', 'wallet', 'transactions', 'withdrawals'));
    }

    public function requestWithdrawal(Request $request)
    {
        $vendor = auth()->user()->vendor;
        $wallet = Wallet::where('owner_type', 'App\\Models\\Vendor')->where('owner_id', $vendor->id)->firstOrFail();

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

        return back()->with('success', 'Withdrawal request submitted. Admin will process within 24 hours.');
    }
}
