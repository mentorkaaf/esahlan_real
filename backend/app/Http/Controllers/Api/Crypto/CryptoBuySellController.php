<?php

namespace App\Http\Controllers\Api\Crypto;

use App\Http\Controllers\Controller;
use App\Models\CryptoWallet;
use App\Models\CryptoOrder;
use App\Models\ExchangeCoin;
use App\Models\ExchangeNetwork;
use App\Models\Wallet;
use App\Services\CryptoMarketService;
use App\Services\CryptoNotificationService;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Payment verification
        if ($request->payment_method === 'epay') {
            $wallet = Wallet::getOrCreateFor('App\\Models\\User', $user->id);
            if ($wallet->balance < $amountUsd) return response()->json(['success'=>false,'message'=>'Insufficient ePay balance. Your balance: $'.number_format($wallet->balance,2)],422);
            $wallet->debit($amountUsd, "Buy {$cryptoAmt} {$coin->symbol}", 'crypto_order', null);
        } elseif ($request->payment_method === 'waafi_pay') {
            if (!$request->payment_reference) return response()->json(['success'=>false,'message'=>'WaafiPay reference required'],422);
            $ptx = DB::table('payment_transactions')
                ->where('reference', $request->payment_reference)
                ->where('user_id', $user->id)
                ->where('status', 'success')
                ->first();
            if (!$ptx) return response()->json(['success'=>false,'message'=>'WaafiPay payment not confirmed. Please try again.'],422);
        }

        $isInstant = in_array($request->payment_method, ['epay','waafi_pay']);
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
            'status'           => $isInstant ? 'completed' : 'pending',
        ]);

        // Credit crypto wallet immediately for instant payments
        if ($order->status === 'completed') {
            $cryptoWallet = CryptoWallet::getOrCreate($user->id, $coin->id, $network->id);
            $cryptoWallet->credit($cryptoAmt, 'buy', "Bought at \${$price}", 'crypto_order', $order->id);
            CryptoNotificationService::buyCompleted($user, $cryptoAmt, $coin->symbol, $amountUsd);
        } else {
            CryptoNotificationService::buyPending($user, $cryptoAmt, $coin->symbol, $amountUsd);
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

        $sellInstant = in_array($request->receive_method, ['epay','waafi_pay']);
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
            'status'        => $sellInstant ? 'completed' : 'pending',
        ]);

        // Credit ePay wallet immediately for sell via epay
        if ($request->receive_method === 'epay') {
            $ePayWallet = Wallet::getOrCreateFor('App\\Models\\User', $user->id);
            $ePayWallet->credit($netUsd,"Sold {$cryptoAmt} {$coin->symbol}",'crypto_order',$order->id,'wallet');
        }

        $statusMsg = $order->status === 'completed'
            ? "Successfully sold {$cryptoAmt} {$coin->symbol} for \${$netUsd}"
            : "Sell order placed. You will receive \${$netUsd} once admin confirms payment.";

        if ($order->status === 'completed') {
            CryptoNotificationService::sellCompleted($user, $cryptoAmt, $coin->symbol, $netUsd);
        } else {
            CryptoNotificationService::sellPending($user, $cryptoAmt, $coin->symbol);
        }

        return response()->json(['success'=>true,'message'=>$statusMsg,'data'=>[
            'uuid'=>$order->uuid,'net_usd'=>$netUsd,'fee_usd'=>$feeUsd,'status'=>$order->status,
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
