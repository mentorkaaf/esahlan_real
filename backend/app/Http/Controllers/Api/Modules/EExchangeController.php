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
            'from'   => 'required|string|size:3',
            'to'     => 'required|string|size:3',
            'amount' => 'required|numeric|min:0.01',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $rate = DB::table('exchange_rates')
            ->where('from_wallet', strtoupper($request->from))
            ->where('to_wallet', strtoupper($request->to))
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
        $v = Validator::make($request->all(), [
            'from'             => 'required|string|size:3',
            'to'               => 'required|string|size:3',
            'amount'           => 'required|numeric|min:1',
            'recipient_phone'  => 'required|string',
            'payment_method'   => 'required|in:wallet,waafi',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user = $request->user();

        // Calculate amount first
        $calcRequest = new Request(['from' => $request->from, 'to' => $request->to, 'amount' => $request->amount]);
        $calcResponse = $this->calculate($calcRequest);
        $calc = json_decode($calcResponse->getContent(), true)['data'] ?? null;

        if (!$calc) return response()->json(['success' => false, 'message' => 'Exchange pair not available'], 404);

        if ($request->payment_method === 'wallet') {
            $wallet = $user->wallet;
            if (!$wallet || $wallet->balance < $request->amount) {
                return response()->json(['success' => false, 'message' => 'Insufficient wallet balance'], 422);
            }

            DB::transaction(function () use ($wallet, $request, $calc) {
                $wallet->decrement('balance', $request->amount);
                DB::table('wallet_transactions')->insert([
                    'wallet_id'   => $wallet->id,
                    'type'        => 'debit',
                    'amount'      => $request->amount,
                    'description' => "eExchange: {$request->from}→{$request->to} to {$request->recipient_phone}",
                    'created_at'  => now(),
                    'updated_at'  => now(),
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
