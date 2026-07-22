<?php

namespace App\Http\Controllers\Api\Crypto;

use App\Http\Controllers\Controller;
use App\Models\CryptoWallet;
use App\Models\CryptoDeposit;
use App\Models\CryptoWithdrawal;
use App\Models\CryptoTransaction;
use App\Models\ExchangeCoin;
use App\Models\ExchangeNetwork;
use App\Services\CryptoMarketService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CryptoWalletController extends Controller
{
    // GET /api/v1/crypto/wallet — portfolio overview
    public function portfolio(Request $request)
    {
        $user  = $request->user();
        $coins = ExchangeCoin::with(['price','networks'])->where('is_active',true)->orderBy('display_order')->get();

        $portfolio  = [];
        $totalUsd   = 0;
        $todayPl    = 0;

        foreach ($coins as $coin) {
            $wallets = CryptoWallet::where('user_id',$user->id)->where('coin_id',$coin->id)->get();
            $totalBalance = $wallets->sum('balance');
            $price   = $coin->price?->effectivePrice() ?? 0;
            $usdVal  = $totalBalance * $price;
            $change  = $coin->price?->change_24h ?? 0;
            $totalUsd += $usdVal;
            $todayPl  += $usdVal * ($change / 100);

            $portfolio[] = [
                'coin'        => CryptoMarketService::formatCoin($coin),
                'balance'     => $totalBalance,
                'usd_value'   => round($usdVal, 2),
                'wallets'     => $wallets->map(fn($w) => [
                    'network_id' => $w->network_id,
                    'network'    => $w->network?->name,
                    'address'    => $w->address,
                    'balance'    => $w->balance,
                    'pending'    => $w->pending_balance,
                    'locked'     => $w->locked_balance,
                ]),
            ];
        }

        return response()->json(['success'=>true,'data'=>[
            'total_usd'   => round($totalUsd, 2),
            'today_pl'    => round($todayPl, 2),
            'today_pl_pct'=> $totalUsd > 0 ? round($todayPl / $totalUsd * 100, 2) : 0,
            'assets'      => $portfolio,
        ]]);
    }

    // GET /api/v1/crypto/wallet/{symbol}/deposit?network_id=
    public function depositAddress(string $symbol, Request $request)
    {
        $user    = $request->user();
        $coin    = ExchangeCoin::where('symbol',strtoupper($symbol))->where('is_active',true)->firstOrFail();
        $network = ExchangeNetwork::where('coin_id',$coin->id)->where('is_active',true)
            ->when($request->network_id, fn($q) => $q->where('id',$request->network_id))
            ->firstOrFail();

        $wallet = CryptoWallet::getOrCreate($user->id, $coin->id, $network->id);

        return response()->json(['success'=>true,'data'=>[
            'symbol'         => $coin->symbol,
            'name'           => $coin->name,
            'network'        => $network->name,
            'chain'          => $network->chain,
            'address'        => $wallet->address,
            'min_deposit'    => $coin->min_deposit,
            'confirmations'  => $network->confirmations_required,
            'contract'       => $network->contract_address,
        ]]);
    }

    // POST /api/v1/crypto/wallet/withdraw
    public function withdraw(Request $request)
    {
        $request->validate([
            'symbol'     => 'required|string|max:20',
            'network_id' => 'required|integer|exists:exchange_networks,id',
            'address'    => 'required|string|max:200',
            'amount'     => 'required|numeric|min:0',
        ]);

        $user    = $request->user();
        $coin    = ExchangeCoin::where('symbol',strtoupper($request->symbol))->where('is_active',true)->firstOrFail();
        $network = ExchangeNetwork::findOrFail($request->network_id);
        $amount  = (float) $request->amount;
        $fee     = max($network->withdrawal_fee, $coin->withdrawal_fee);
        $netAmt  = $amount - $fee;

        if (!$coin->withdrawal_enabled) return response()->json(['success'=>false,'message'=>'Withdrawals disabled for '.$coin->symbol],422);
        if ($amount < $coin->min_withdrawal) return response()->json(['success'=>false,'message'=>"Minimum withdrawal is {$coin->min_withdrawal} {$coin->symbol}"],422);
        if ($netAmt <= 0) return response()->json(['success'=>false,'message'=>'Amount too small to cover network fee'],422);

        $wallet = CryptoWallet::getOrCreate($user->id, $coin->id, $network->id);
        if ($wallet->balance < $amount) return response()->json(['success'=>false,'message'=>'Insufficient balance'],422);

        // Debit full amount (fee is included within amount, not additional)
        $wallet->debit($amount, 'withdrawal', "Withdraw to {$request->address}", 0, 'crypto_withdrawal', null);

        $wd = \App\Models\CryptoWithdrawal::create([
            'uuid'       => (string) Str::uuid(),
            'user_id'    => $user->id,
            'coin_id'    => $coin->id,
            'network_id' => $network->id,
            'to_address' => $request->address,
            'amount'     => $amount,
            'fee'        => $fee,
            'net_amount' => $netAmt,
            'status'     => 'pending',
        ]);

        // FCM
        try {
            if ($user->fcm_token) \App\Services\FcmService::sendToToken($user->fcm_token,'Withdrawal Submitted',"Your {$coin->symbol} withdrawal of {$amount} is under review.",[],null);
        } catch (\Throwable) {}

        return response()->json(['success'=>true,'message'=>'Withdrawal submitted. Admin will process it shortly.','data'=>['uuid'=>$wd->uuid,'status'=>'pending']]);
    }

    // POST /api/v1/crypto/wallet/transfer — internal user-to-user
    public function transfer(Request $request)
    {
        $request->validate([
            'symbol'    => 'required|string|max:20',
            'network_id'=> 'required|integer|exists:exchange_networks,id',
            'to_phone'  => 'required|string|min:6|max:30',
            'amount'    => 'required|numeric|min:0',
        ]);

        $user      = $request->user();
        $coin      = ExchangeCoin::where('symbol',strtoupper($request->symbol))->firstOrFail();
        $network   = ExchangeNetwork::findOrFail($request->network_id);
        $amount    = (float) $request->amount;
        $cleanPhone = preg_replace('/^0+/', '', preg_replace('/\D/', '', $request->to_phone));
        $recipient  = \App\Models\User::where('phone', 'like', '%' . $cleanPhone)
            ->orWhere('phone', $request->to_phone)->first();

        if (!$recipient) return response()->json(['success'=>false,'message'=>'User not found'],404);
        if ($recipient->id === $user->id) return response()->json(['success'=>false,'message'=>'Cannot transfer to yourself'],422);

        $fromWallet = CryptoWallet::getOrCreate($user->id, $coin->id, $network->id);
        if ($fromWallet->balance < $amount) return response()->json(['success'=>false,'message'=>'Insufficient balance'],422);

        $fromWallet->debit($amount,'transfer_out',"Transfer to {$recipient->name}",0,'user_transfer',null);
        $toWallet = CryptoWallet::getOrCreate($recipient->id, $coin->id, $network->id);
        $toWallet->credit($amount,'transfer_in',"Transfer from {$user->name}",'user_transfer',null);

        try {
            if ($recipient->fcm_token) \App\Services\FcmService::sendToToken($recipient->fcm_token,'Crypto Received',"{$user->name} sent you {$amount} {$coin->symbol}",[],null);
        } catch (\Throwable) {}

        return response()->json(['success'=>true,'message'=>'Transfer successful']);
    }

    // GET /api/v1/crypto/wallet/transactions
    public function transactions(Request $request)
    {
        $user = $request->user();
        $q = CryptoTransaction::with('coin')
            ->where('user_id',$user->id)
            ->when($request->coin, fn($q) => $q->whereHas('coin',fn($c) => $c->where('symbol',strtoupper($request->coin))))
            ->when($request->type, fn($q) => $q->where('type',$request->type))
            ->orderByDesc('created_at');

        return response()->json(['success'=>true,'data'=>$q->paginate(30)]);
    }
}
