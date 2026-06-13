<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\User;
use App\Services\FcmService;
use App\Helpers\AppSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminWalletController extends Controller
{
    public function index()
    {
        $users = DB::table('users')
            ->leftJoin('wallets', function ($j) {
                $j->on('wallets.owner_id', '=', 'users.id')
                  ->where('wallets.owner_type', 'App\\Models\\User');
            })
            ->select('users.id', 'users.name', 'users.email', 'users.phone',
                     'wallets.id as wallet_id', 'wallets.balance', 'wallets.total_earned', 'wallets.total_withdrawn')
            ->orderByDesc('wallets.balance')
            ->paginate(30);

        $stats = [
            'total_balance'  => DB::table('wallets')->where('owner_type', 'App\\Models\\User')->sum('balance'),
            'total_topups'   => DB::table('payment_transactions')->where('transaction_type', 'topup_done')->sum('amount'),
            'pending_withdrawals' => DB::table('withdrawal_requests')->where('status', 'pending')->sum('amount'),
            'user_count'     => DB::table('wallets')->where('owner_type', 'App\\Models\\User')->count(),
        ];

        return view('admin.wallet.index', compact('users', 'stats'));
    }

    public function transactions(Request $request)
    {
        $q = DB::table('transactions')
            ->join('wallets', 'wallets.id', '=', 'transactions.wallet_id')
            ->join('users', function ($j) {
                $j->on('users.id', '=', 'wallets.owner_id')
                  ->where('wallets.owner_type', 'App\\Models\\User');
            })
            ->select('transactions.*', 'users.name as user_name', 'users.email as user_email', 'wallets.balance as current_balance')
            ->orderByDesc('transactions.created_at');

        if ($request->user_id) $q->where('users.id', $request->user_id);
        if ($request->search) {
            $s = $request->search;
            $q->where(function ($qq) use ($s) {
                $qq->where('users.name', 'like', "%$s%")
                   ->orWhere('users.email', 'like', "%$s%")
                   ->orWhere('transactions.note', 'like', "%$s%");
            });
        }
        if ($request->type) $q->where('transactions.type', $request->type);

        $filterUser = $request->user_id
            ? DB::table('users')->where('id', $request->user_id)->first()
            : null;

        $transactions = $q->paginate(40)->withQueryString();

        return view('admin.wallet.transactions', compact('transactions', 'filterUser'));
    }

    public function creditUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount'  => 'required|numeric|min:0.01|max:100000',
            'note'    => 'required|string|max:300',
        ]);

        $wallet = Wallet::getOrCreateFor('App\\Models\\User', $request->user_id);
        $wallet->credit((float) $request->amount, 'Admin credit: ' . $request->note, null, null, 'admin');

        try{$u=User::find($request->user_id);if($u?->fcm_token)FcmService::sendWalletCredit($u->fcm_token,(float)$request->amount,(float)$wallet->fresh()->balance);}catch(\Throwable $er){}
        return back()->with('success', 'Credit of $' . $request->amount . ' added to user wallet.');
    }

    public function withdrawals()
    {
        $requests = DB::table('withdrawal_requests')
            ->where('owner_type', 'App\\Models\\User')
            ->join('users', 'users.id', '=', 'withdrawal_requests.owner_id')
            ->select('withdrawal_requests.*', 'users.name as user_name', 'users.phone as user_phone')
            ->orderByDesc('withdrawal_requests.created_at')
            ->paginate(30);

        return view('admin.wallet.withdrawals', compact('requests'));
    }

    public function approveWithdrawal(Request $request, $id)
    {
        DB::table('withdrawal_requests')->where('id', $id)->update([
            'status'     => 'approved',
            'updated_at' => now(),
        ]);
        try{$r=DB::table('withdrawal_requests')->find($id);$u2=$r?User::find($r->owner_id):null;if($u2?->fcm_token)FcmService::sendWithdrawalApproved($u2->fcm_token,(float)($r->amount??0));}catch(\Throwable $er){}
        return back()->with('success', 'Withdrawal approved.');
    }

    public function rejectWithdrawal(Request $request, $id)
    {
        $wr = DB::table('withdrawal_requests')->find($id);
        if (!$wr || $wr->status !== 'pending') return back()->with('error', 'Invalid request.');

        DB::transaction(function () use ($wr, $id) {
            // Refund amount back to wallet
            $wallet = Wallet::getOrCreateFor('App\\Models\\User', $wr->owner_id);
            $wallet->credit((float) $wr->amount, 'Withdrawal rejected - refunded', null, null, 'refund');
            DB::table('withdrawal_requests')->where('id', $id)->update(['status' => 'rejected', 'updated_at' => now()]);
        });

        try{$u3=User::find($wr->owner_id);if($u3?->fcm_token)FcmService::sendWithdrawalRejected($u3->fcm_token,(float)$wr->amount);}catch(\Throwable $er){}
        return back()->with('success', 'Withdrawal rejected and amount refunded.');
    }

    // Settings page for Waafi Pay credentials
    public function settings()
    {
        $settings = [
            'waafi_merchant_uid' => AppSettings::get('waafi_merchant_uid', ''),
            'waafi_api_user_id'  => AppSettings::get('waafi_api_user_id', ''),
            'waafi_api_key'      => AppSettings::get('waafi_api_key', ''),
            'waafi_api_url'      => AppSettings::get('waafi_api_url', 'https://api.waafipay.net/asm'),
            'waafi_description'  => AppSettings::get('waafi_description', 'eSahlan Payment'),
        ];
        return view('admin.wallet.settings', compact('settings'));
    }

    public function saveSettings(Request $request)
    {
        $fields = ['waafi_merchant_uid', 'waafi_api_user_id', 'waafi_api_key', 'waafi_api_url', 'waafi_description'];
        foreach ($fields as $key) {
            if ($request->has($key)) {
                AppSettings::set($key, $request->input($key));
            }
        }
        return back()->with('success', 'Payment gateway settings saved.');
    }
}
