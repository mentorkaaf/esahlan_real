<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $user   = $request->user();
        $wallet = Wallet::getOrCreateFor('App\\Models\\User', $user->id);

        return response()->json([
            'success' => true,
            'data'    => [
                'balance'        => $wallet->balance ?? 0,
                'currency'       => $wallet->currency ?? 'USD',
                'loyalty_points' => $user->loyalty_points ?? 0,
            ],
        ]);
    }

    public function transactions(Request $request)
    {
        $user   = $request->user();
        $wallet = Wallet::getOrCreateFor('App\\Models\\User', $user->id);

        $txns = $wallet->transactions()->latest()->paginate(20);

        return response()->json(['success' => true, 'data' => $txns]);
    }

    public function topup(Request $request)
    {
        $v = Validator::make($request->all(), [
            'amount'  => 'required|numeric|min:1|max:5000',
            'gateway' => 'required|in:waafi,evc',
            'phone'   => 'required|string',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user   = $request->user();
        $wallet = Wallet::getOrCreateFor('App\\Models\\User', $user->id);
        $amount = (float) $request->amount;
        $gateway = $request->gateway;

        $wallet->credit($amount, "Wallet top-up via {$gateway}", null, null, $gateway);

        return response()->json([
            'success' => true,
            'message' => 'Wallet topped up successfully',
            'data'    => ['balance' => $wallet->fresh()->balance],
        ]);
    }

    public function requestWithdrawal(Request $request)
    {
        $v = Validator::make($request->all(), [
            'amount'         => 'required|numeric|min:1',
            'payment_method' => 'required|in:waafi,evc,bank',
            'account_number' => 'required|string',
            'account_name'   => 'required|string',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user   = $request->user();
        $wallet = Wallet::getOrCreateFor('App\\Models\\User', $user->id);
        $amount = (float) $request->amount;

        if ($wallet->balance < $amount) {
            return response()->json(['success' => false, 'message' => 'Insufficient wallet balance'], 422);
        }

        DB::transaction(function () use ($wallet, $user, $amount, $request) {
            $wallet->debit($amount, 'Withdrawal request pending');

            DB::table('withdrawal_requests')->insert([
                'owner_type'     => 'App\\Models\\User',
                'owner_id'       => $user->id,
                'amount'         => $amount,
                'payment_method' => $request->payment_method,
                'account_number' => $request->account_number,
                'account_name'   => $request->account_name,
                'status'         => 'pending',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        });

        return response()->json(['success' => true, 'message' => 'Withdrawal request submitted. Admin will process within 24 hours.']);
    }

    public function loyaltyPoints(Request $request)
    {
        $user = $request->user();

        $history = DB::table('loyalty_transactions')
            ->where('user_id', $user->id)
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'points'  => $user->loyalty_points ?? 0,
                'history' => $history,
            ],
        ]);
    }

    public function referral(Request $request)
    {
        $user = $request->user();

        $referrals = DB::table('referrals')
            ->where('referrer_id', $user->id)
            ->join('users', 'users.id', '=', 'referrals.referred_id')
            ->select('users.name', 'referrals.status', 'referrals.reward_given', 'referrals.created_at')
            ->latest('referrals.created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'referral_code'  => $user->referral_code,
                'referral_count' => $referrals->count(),
                'total_earned'   => $referrals->where('reward_given', true)->count() * (float) settings('referral_reward', 1),
                'referrals'      => $referrals,
            ],
        ]);
    }
}
