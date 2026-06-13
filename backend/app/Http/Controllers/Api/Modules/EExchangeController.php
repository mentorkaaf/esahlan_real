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
        $request->merge(['from' => $fromVal, 'to' => $toVal]);

        $rate = DB::table('exchange_rates')
            ->where('from_wallet', $fromVal)
            ->where('to_wallet', $toVal)
            ->where('is_active', true)
            ->first();

        if (!$rate) {
            return response()->json(['success' => false, 'message' => 'Exchange pair not available'], 404);
        }

        $amount    = (float) $request->amount;
        $fee       = $rate->fee_type === 'percentage' ? round($amount * $rate->fee_value / 100, 2) : $rate->fee_value;
        $converted = round(($amount - $fee) * $rate->rate, 2);

        return response()->json([
            'success' => true,
            'data'    => [
                'from'      => $request->from,
                'to'        => $request->to,
                'amount'    => $amount,
                'fee'       => $fee,
                'rate'      => $rate->rate,
                'converted' => $converted,
            ],
        ]);
    }

    public function transfer(Request $request)
    {
        $fromVal = strtoupper($request->from_wallet ?? $request->from ?? '');
        $toVal   = strtoupper($request->to_wallet   ?? $request->to   ?? '');
        $request->merge(['from' => $fromVal, 'to' => $toVal]);

        $v = Validator::make($request->all(), [
            'from'             => 'required|string|max:20',
            'to'               => 'required|string|max:20',
            'amount'           => 'required|numeric|min:0.01',
            'recipient_phone'  => 'nullable|string',
            'payment_method'   => 'nullable|string|in:wallet,waafi,cod',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user = $request->user();

        // Calculate amount first
        $calcRequest = new Request(['from' => $request->from, 'to' => $request->to, 'amount' => $request->amount]);
        $calcResponse = $this->calculate($calcRequest);
        $calc = json_decode($calcResponse->getContent(), true)['data'] ?? null;

        if (!$calc) return response()->json(['success' => false, 'message' => 'Exchange pair not available'], 404);

        if (($request->payment_method ?? 'wallet') === 'wallet') {
            $wallet = $user->wallet;
            if (!$wallet || $wallet->balance < $request->amount) {
                return response()->json(['success' => false, 'message' => 'Insufficient wallet balance'], 422);
            }

            DB::transaction(function () use ($wallet, $request, $calc) {
                $balanceBefore = (float) $wallet->balance;
                $wallet->decrement("balance", $request->amount);
                DB::table("transactions")->insert([
                    "uuid"           => (string) \Illuminate\Support\Str::uuid(),
                    "wallet_id"      => $wallet->id,
                    "type"           => "debit",
                    "amount"         => $request->amount,
                    "balance_before" => $balanceBefore,
                    "balance_after"  => $balanceBefore - $request->amount,
                    "note"           => "eExchange: {$request->from} to {$request->to} - {$request->recipient_phone}",
                    "payment_method" => "wallet",
                    "status"         => "completed",
                    "created_at"     => now(),
                    "updated_at"     => now(),
                ]);
            });
        }

        // TODO: actual exchange/remittance integration

        return response()->json([
            'success' => true,
            'message' => "Transfer of {$calc['converted']} {$request->to} sent to {$request->recipient_phone}",
            'data'    => array_merge($calc, ['reference' => 'EXC-' . strtoupper(Str::random(10))]),
        ]);
    }
}
