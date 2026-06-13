<?php

namespace App\Http\Controllers\Api\Modules;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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
        $fee       = $rate->fee_type === 'percentage' ? round($amount * $rate->fee_value / 100, 2) : (float)$rate->fee_value;
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
                // Legacy keys kept for compatibility
                'from'      => $fromVal,
                'to'        => $toVal,
                'converted' => $converted,
                'you_receive' => $converted,
            ],
        ]);
    }

    public function transfer(Request $request)
    {
        $fromVal = strtoupper($request->from_wallet ?? $request->from ?? '');
        $toVal   = strtoupper($request->to_wallet   ?? $request->to   ?? '');
        $request->merge(['from' => $fromVal, 'to' => $toVal]);

        $v = Validator::make($request->all(), [
            'from'            => 'required|string|max:20',
            'to'              => 'required|string|max:20',
            'amount'          => 'required|numeric|min:0.01',
            'recipient_phone' => 'required|string|min:7|max:30',
            'payment_method'  => 'nullable|string|in:wallet,waafi,cod',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user = $request->user();

        // Calculate
        $calcRequest  = new Request(['from' => $fromVal, 'to' => $toVal, 'amount' => $request->amount]);
        $calcResponse = $this->calculate($calcRequest);
        $calc         = json_decode($calcResponse->getContent(), true)['data'] ?? null;

        if (!$calc) {
            return response()->json(['success' => false, 'message' => 'Exchange pair not available'], 404);
        }

        $reference = 'EXC-' . strtoupper(Str::random(10));

        // Deduct from wallet if payment method is wallet
        if (($request->payment_method ?? 'wallet') === 'wallet') {
            $wallet = $user->wallet;
            if (!$wallet || $wallet->balance < $request->amount) {
                return response()->json(['success' => false, 'message' => 'Insufficient wallet balance'], 422);
            }

            DB::transaction(function () use ($wallet, $request, $calc, $user, $reference) {
                $balanceBefore = (float) $wallet->balance;
                $wallet->decrement('balance', $request->amount);

                DB::table('transactions')->insert([
                    'uuid'           => (string) Str::uuid(),
                    'wallet_id'      => $wallet->id,
                    'type'           => 'debit',
                    'amount'         => $request->amount,
                    'balance_before' => $balanceBefore,
                    'balance_after'  => $balanceBefore - $request->amount,
                    'note'           => "eExchange: {$request->from} → {$request->to} → {$request->recipient_phone} (ref: {$reference})",
                    'payment_method' => 'wallet',
                    'status'         => 'completed',
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);

                DB::table('exchange_orders')->insert([
                    'reference'        => $reference,
                    'user_id'          => $user->id,
                    'from_wallet'      => $request->from,
                    'to_wallet'        => $request->to,
                    'sent_amount'      => $request->amount,
                    'fee_amount'       => $calc['fee'],
                    'rate'             => $calc['rate'],
                    'converted_amount' => $calc['converted_amount'],
                    'recipient_phone'  => $request->recipient_phone,
                    'status'           => 'completed',
                    'note'             => $request->note ?? null,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            });
        }

        return response()->json([
            'success' => true,
            'message' => "{$calc['converted_amount']} {$request->to} sent to {$request->recipient_phone}",
            'data'    => [
                'reference'        => $reference,
                'from_wallet'      => $request->from,
                'to_wallet'        => $request->to,
                'sent_amount'      => $request->amount,
                'fee'              => $calc['fee'],
                'rate'             => $calc['rate'],
                'converted_amount' => $calc['converted_amount'],
                'recipient_phone'  => $request->recipient_phone,
                'status'           => 'completed',
            ],
        ]);
    }
}
