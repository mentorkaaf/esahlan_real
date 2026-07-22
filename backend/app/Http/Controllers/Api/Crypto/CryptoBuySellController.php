<?php

namespace App\Http\Controllers\Api\Crypto;

use App\Http\Controllers\Controller;
use App\Models\CryptoWallet;
use App\Models\CryptoOrder;
use App\Models\ExchangeCoin;
use App\Models\ExchangeNetwork;
use App\Models\Wallet;
use App\Services\CryptoMarketService;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CryptoBuySellController extends Controller
{
    // POST /api/v1/crypto/quote
    public function quote(Request $request)
    {
        $request->validate(['symbol'=>'required|string','side'=>'required|in:buy,sell','amount_usd'=>'required|numeric|min:1']);
        $coin  = ExchangeCoin::with('price')->where('symbol',strtoupper($request->symbol))->firstOrFail();
        $price = $coin->price?->effectivePrice() ?? 0;
        if ($price <= 0) return response()->json(['success'=>false,'message'=>'Price unavailable'],422);

        $feePct      = $request->side === 'buy' ? $coin->buy_fee_pct : $coin->sell_fee_pct;
        $amountUsd   = (float) $request->amount_usd;
        $feeUsd      = round($amountUsd * $feePct / 100, 4);
        $netUsd      = $request->side === 'buy' ? $amountUsd - $feeUsd : $amountUsd;
        $cryptoAmt   = round($netUsd / $price, $coin->decimals);

        return response()->json(['success'=>true,'data'=>[
            'symbol'       => $coin->symbol,
            'side'         => $request->side,
            'price_usd'    => $price,
            'amount_usd'   => $amountUsd,
            'fee_usd'      => $feeUsd,
            'fee_pct'      => $feePct,
            'crypto_amount'=> $cryptoAmt,
            'you_receive'  => $request->side === 'buy' ? "$cryptoAmt {$coin->symbol}" : "\$".round($netUsd - $feeUsd, 2),
        ]]);
    }

    // POST /api/v1/crypto/buy
    public function buy(Request $request)
    {
        $request->validate([
            'symbol'          => 'required|string|max:20',
            'network_id'      => 'required|integer|exists:exchange_networks,id',
            'amount_usd'      => 'required|numeric|min:1',
            'payment_method'  => 'required|in:epay,waafi_pay,evc,edahab',
            'payment_reference'=> 'nullable|string|max:200',
        ]);

        $user   = $request->user();
        $coin   = ExchangeCoin::with('price')->where('symbol',strtoupper($request->symbol))->where('buy_enabled',true)->firstOrFail();
        $network= ExchangeNetwork::findOrFail($request->network_id);
        $price  = $coin->price?->effectivePrice() ?? 0;
        if ($price <= 0) return response()->json(['success'=>false,'message'=>'Price unavailable'],422);

        $amountUsd  = (float) $request->amount_usd;
        $feePct     = $coin->buy_fee_pct;
        $feeUsd     = round($amountUsd * $feePct / 100, 4);
        $netUsd     = $amountUsd - $feeUsd;
        $cryptoAmt  = round($netUsd / $price, $coin->decimals);

        // Deduct ePay wallet
        if ($request->payment_method === 'epay') {
            $wallet = Wallet::getOrCreateFor('App\\Models\\User', $user->id);
            if ($wallet->balance < $amountUsd) return response()->json(['success'=>false,'message'=>'Insufficient ePay balance'],422);
            $wallet->debit($amountUsd, "Buy {$cryptoAmt} {$coin->symbol}", 'crypto_order', null);
        }

        $order = CryptoOrder::create([
            'uuid'             => (string) Str::uuid(),
            'user_id'          => $user->id,
            'coin_id'          => $coin->id,
            'side'             => 'buy',
            'crypto_amount'    => $cryptoAmt,
            'price_usd'        => $price,
            'total_usd'        => $amountUsd,
            'fee_usd'          => $feeUsd,
            'fee_pct'          => $feePct,
            'payment_method'   => $request->payment_method,
            'payment_reference'=> $request->payment_reference,
            'status'           => $request->payment_method === 'epay' ? 'completed' : 'pending',
        ]);

        // Credit crypto wallet immediately for ePay
        if ($order->status === 'completed') {
            $cryptoWallet = CryptoWallet::getOrCreate($user->id, $coin->id, $network->id);
            $cryptoWallet->credit($cryptoAmt, 'buy', "Bought at \${$price}", 'crypto_order', $order->id);
            try {
                if ($user->fcm_token) FcmService::sendToToken($user->fcm_token,'Buy Order Completed',"You bought {$cryptoAmt} {$coin->symbol} for \${$amountUsd}",[],null);
            } catch (\Throwable) {}
        }

        return response()->json(['success'=>true,'message'=> $order->status === 'completed' ? "Successfully bought {$cryptoAmt} {$coin->symbol}" : 'Order placed. Awaiting payment confirmation.','data'=>[
            'uuid'         => $order->uuid,
            'crypto_amount'=> $cryptoAmt,
            'total_usd'    => $amountUsd,
            'fee_usd'      => $feeUsd,
            'status'       => $order->status,
        ]]);
    }

    // POST /api/v1/crypto/sell
    public function sell(Request $request)
    {
        $request->validate([
            'symbol'        => 'required|string|max:20',
            'network_id'    => 'required|integer|exists:exchange_networks,id',
            'crypto_amount' => 'required|numeric|min:0',
            'receive_method'=> 'required|in:epay,waafi_pay,evc,edahab',
        ]);

        $user   = $request->user();
        $coin   = ExchangeCoin::with('price')->where('symbol',strtoupper($request->symbol))->where('sell_enabled',true)->firstOrFail();
        $network= ExchangeNetwork::findOrFail($request->network_id);
        $price  = $coin->price?->effectivePrice() ?? 0;
        if ($price <= 0) return response()->json(['success'=>false,'message'=>'Price unavailable'],422);

        $cryptoAmt  = (float) $request->crypto_amount;
        $grossUsd   = round($cryptoAmt * $price, 4);
        $feePct     = $coin->sell_fee_pct;
        $feeUsd     = round($grossUsd * $feePct / 100, 4);
        $netUsd     = round($grossUsd - $feeUsd, 4);

        $cryptoWallet = CryptoWallet::getOrCreate($user->id, $coin->id, $network->id);
        if ($cryptoWallet->balance < $cryptoAmt) return response()->json(['success'=>false,'message'=>'Insufficient crypto balance'],422);

        $cryptoWallet->debit($cryptoAmt,'sell',"Sell for \${$netUsd}",0,'crypto_order',null);

        $order = CryptoOrder::create([
            'uuid'          => (string) Str::uuid(),
            'user_id'       => $user->id,
            'coin_id'       => $coin->id,
            'side'          => 'sell',
            'crypto_amount' => $cryptoAmt,
            'price_usd'     => $price,
            'total_usd'     => $grossUsd,
            'fee_usd'       => $feeUsd,
            'fee_pct'       => $feePct,
            'payment_method'=> $request->receive_method,
            'status'        => 'completed',
        ]);

        // Credit ePay wallet
        if ($request->receive_method === 'epay') {
            $ePayWallet = Wallet::getOrCreateFor('App\\Models\\User', $user->id);
            $ePayWallet->credit($netUsd,"Sold {$cryptoAmt} {$coin->symbol}",'crypto_order',$order->id,'wallet');
        }

        try {
            if ($user->fcm_token) FcmService::sendToToken($user->fcm_token,'Sell Order Completed',"Sold {$cryptoAmt} {$coin->symbol} for \${$netUsd}",[],null);
        } catch (\Throwable) {}

        return response()->json(['success'=>true,'message'=>"Successfully sold {$cryptoAmt} {$coin->symbol} for \${$netUsd}",'data'=>[
            'uuid'=>$order->uuid,'net_usd'=>$netUsd,'fee_usd'=>$feeUsd,'status'=>'completed',
        ]]);
    }

    // GET /api/v1/crypto/orders
    public function orders(Request $request)
    {
        $orders = CryptoOrder::with('coin')->where('user_id',$request->user()->id)
            ->when($request->side, fn($q) => $q->where('side',$request->side))
            ->orderByDesc('created_at')->paginate(20);
        return response()->json(['success'=>true,'data'=>$orders]);
    }
}
