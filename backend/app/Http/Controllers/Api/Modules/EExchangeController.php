<?php

namespace App\Http\Controllers\Api\Modules;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Wallet;
use Illuminate\Support\Str;

class EExchangeController extends Controller
{
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
            'payment_method'  => 'nullable|string|in:wallet,waafi_pay',
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
