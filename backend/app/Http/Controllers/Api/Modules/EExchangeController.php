<?php

namespace App\Http\Controllers\Api\Modules;

use App\Http\Controllers\Controller;
use App\Services\AdminAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Wallet;
use Illuminate\Support\Str;

class EExchangeController extends Controller
{
    // ── Saved Accounts ────────────────────────────────────────────────────────

    /** GET /api/v1/eexchange/accounts */
    public function myAccounts(Request $request)
    {
        $accounts = DB::table('exchange_accounts')
            ->where('user_id', $request->user()->id)
            ->orderBy('wallet_type')
            ->get();
        return response()->json(['success' => true, 'data' => $accounts]);
    }

    /** POST /api/v1/eexchange/accounts */
    public function addAccount(Request $request)
    {
        $v = Validator::make($request->all(), [
            'wallet_type'  => 'required|string|in:evc,edahab,jeep,premier,ebesa,usdt',
            'phone_number' => 'required|string|min:5|max:30',
            'label'        => 'nullable|string|max:60',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'message' => $v->errors()->first()], 422);

        $userId = $request->user()->id;

        // Upsert — update if same wallet type already saved
        DB::table('exchange_accounts')->updateOrInsert(
            ['user_id' => $userId, 'wallet_type' => $request->wallet_type],
            [
                'phone_number' => trim($request->phone_number),
                'label'        => $request->label ?? null,
                'updated_at'   => now(),
                'created_at'   => now(),
            ]
        );

        $account = DB::table('exchange_accounts')
            ->where('user_id', $userId)
            ->where('wallet_type', $request->wallet_type)
            ->first();

        return response()->json(['success' => true, 'data' => $account]);
    }

    /** DELETE /api/v1/eexchange/accounts/{id} */
    public function removeAccount(Request $request, int $id)
    {
        $deleted = DB::table('exchange_accounts')
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->delete();

        if (!$deleted) return response()->json(['success' => false, 'message' => 'Account not found'], 404);
        return response()->json(['success' => true]);
    }

    // ── Exchange Rates ────────────────────────────────────────────────────────

    public function rates()
    {
        $rates = DB::table('exchange_rates')->where('is_active', true)->get();
        return response()->json(['success' => true, 'data' => $rates]);
    }

    public function calculate(Request $request)
    {
        $v = Validator::make($request->all(), [
            'from'        => 'required_without:from_wallet|string|max:20',
            'from_wallet' => 'required_without:from|string|max:20',
            'to'          => 'required_without:to_wallet|string|max:20',
            'to_wallet'   => 'required_without:to|string|max:20',
            'amount'      => 'required|numeric|min:0.01',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $fromVal = strtoupper($request->from_wallet ?? $request->from);
        $toVal   = strtoupper($request->to_wallet   ?? $request->to);

        $rate = DB::table('exchange_rates')
            ->where('from_wallet', $fromVal)
            ->where('to_wallet', $toVal)
            ->where('is_active', true)
            ->first();

        if (!$rate) {
            return response()->json(['success' => false, 'message' => 'Exchange pair not available'], 404);
        }

        $amount    = (float) $request->amount;
        $fee       = $rate->fee_type === 'percentage'
            ? round($amount * $rate->fee_value / 100, 2)
            : (float) $rate->fee_value;
        $converted = round(($amount - $fee) * $rate->rate, 2);

        return response()->json([
            'success' => true,
            'data'    => [
                'from_wallet'      => $fromVal,
                'to_wallet'        => $toVal,
                'amount'           => $amount,
                'fee'              => $fee,
                'rate'             => $rate->rate,
                'converted_amount' => $converted,
                // legacy keys
                'from'       => $fromVal,
                'to'         => $toVal,
                'converted'  => $converted,
                'you_receive'=> $converted,
            ],
        ]);
    }

    public function transfer(Request $request)
    {
        $fromVal = strtoupper($request->from_wallet ?? $request->from ?? '');
        $toVal   = strtoupper($request->to_wallet   ?? $request->to   ?? '');

        $v = Validator::make(array_merge($request->all(), [
            'from' => $fromVal,
            'to'   => $toVal,
        ]), [
            'from'            => 'required|string|max:20',
            'to'              => 'required|string|max:20',
            'amount'          => 'required|numeric|min:0.01',
            'recipient_phone' => 'required|string|min:6|max:30',
            'payment_method'  => 'nullable|string|in:wallet,waafi_pay,mobile_pay',
        ]);
        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'message' => $v->errors()->first(),
                'errors'  => $v->errors(),
            ], 422);
        }

        $user          = $request->user();
        $paymentMethod = $request->payment_method ?? 'wallet';
        $sentAmount    = (float) $request->amount;

        // Calculate exchange
        $calcRequest  = new Request(['from' => $fromVal, 'to' => $toVal, 'amount' => $sentAmount]);
        $calcResponse = $this->calculate($calcRequest);
        $calc         = json_decode($calcResponse->getContent(), true)['data'] ?? null;

        if (!$calc) {
            return response()->json(['success' => false, 'message' => "Exchange pair {$fromVal}→{$toVal} not available"], 404);
        }

        // Wallet payment: check balance and deduct
        if ($paymentMethod === 'wallet') {
            $wallet  = Wallet::getOrCreateFor('App\\Models\\User', $user->id);
            $balance = (float) $wallet->balance;
            if ($balance < $sentAmount) {
                return response()->json([
                    'success' => false,
                    'message' => "Insufficient wallet balance. You have \${$balance}, need \${$sentAmount}.",
                ], 422);
            }
        }
        // Waafi Pay: reference is logged, no further server deduction needed

        $reference = 'EXC-' . strtoupper(Str::random(10));

        $exchangeId = DB::table('exchange_orders')->insertGetId([
            'reference'          => $reference,
            'user_id'            => $user->id,
            'from_wallet'        => $fromVal,
            'to_wallet'          => $toVal,
            'sent_amount'        => $sentAmount,
            'fee_amount'         => $calc['fee'],
            'rate'               => $calc['rate'],
            'converted_amount'   => $calc['converted_amount'],
            'recipient_phone'    => $request->recipient_phone,
            'status'             => 'pending',
            'note'               => $request->payment_reference
                                    ? "Payment ref: {$request->payment_reference}"
                                    : ($request->note ?? null),
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        if ($paymentMethod === 'wallet') {
            $w = Wallet::getOrCreateFor('App\\Models\\User', $user->id);
            $w->debit($sentAmount, "eExchange {$fromVal}→{$toVal} ref:{$reference}", 'exchange_order', $exchangeId);
        }

        // ── Admin alert email ─────────────────────────────────────────────
        try {
            AdminAlertService::send('new_order', "New eExchange Order {$reference}", [
                'Reference'   => $reference,
                'Module'      => 'EEXCHANGE',
                'From'        => $fromVal,
                'To'          => $toVal,
                'Sent Amount' => number_format($sentAmount, 2) . ' ' . $fromVal,
                'Converted'   => number_format($calc['converted_amount'], 2) . ' ' . $toVal,
                'Fee'         => number_format($calc['fee'], 2),
                'Payment'     => strtoupper($paymentMethod),
                'Status'      => 'Pending',
                'Placed At'   => now()->format('d M Y H:i') . ' UTC',
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[AdminAlert][eexchange] ' . $e->getMessage());
        }

        // ── Push notification: order placed ──────────────────────────────
        try {
            if (!empty($user->fcm_token)) {
                \App\Services\FcmService::sendOrderUpdate(
                    $user->fcm_token,
                    $reference,
                    'pending',
                    $exchangeId,
                    'eexchange',
                );
            }
        } catch (\Throwable) {}

        return response()->json([
            'success' => true,
            'message' => 'Exchange order created successfully.',
            'data'    => [
                'reference'        => $reference,
                'from_wallet'      => $fromVal,
                'to_wallet'        => $toVal,
                'sent_amount'      => $sentAmount,
                'fee'              => $calc['fee'],
                'rate'             => $calc['rate'],
                'converted_amount' => $calc['converted_amount'],
                'recipient_phone'  => $request->recipient_phone,
                'payment_method'   => $paymentMethod,
                'status'           => 'pending',
            ],
        ]);
    }
}
