<?php

namespace App\Http\Controllers\Api\Crypto;

use App\Http\Controllers\Controller;
use App\Models\{CryptoWallet, ExchangeCoin, ExchangeNetwork, P2pAd, P2pOrder, P2pEscrow, P2pMessage, P2pDispute};
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class P2pController extends Controller
{
    // ── Ads ──────────────────────────────────────────────────────────────────

    public function ads(Request $request)
    {
        $q = P2pAd::with(['user','coin'])
            ->where('status','active')
            ->when($request->type, fn($q)  => $q->where('type',$request->type))
            ->when($request->coin, fn($q)  => $q->whereHas('coin',fn($c)=>$c->where('symbol',strtoupper($request->coin))))
            ->when($request->payment, fn($q)=> $q->whereJsonContains('payment_methods',$request->payment))
            ->orderByDesc('is_merchant')->orderByDesc('completed_count');

        return response()->json(['success'=>true,'data'=>$q->paginate(20)->through(fn($ad) => $this->_formatAd($ad, $request->user()))]);
    }

    public function createAd(Request $request)
    {
        $request->validate([
            'coin_symbol'     => 'required|string|max:20',
            'type'            => 'required|in:buy,sell',
            'amount'          => 'required|numeric|min:0',
            'price_usd'       => 'required|numeric|min:0',
            'payment_methods' => 'required|array|min:1',
            'min_order_usd'   => 'required|numeric|min:1',
            'max_order_usd'   => 'required|numeric|min:1',
            'terms'           => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $coin = ExchangeCoin::where('symbol',strtoupper($request->coin_symbol))->where('p2p_enabled',true)->firstOrFail();

        // For sell ads, lock crypto in escrow reserve
        if ($request->type === 'sell') {
            $network = ExchangeNetwork::where('coin_id',$coin->id)->where('is_active',true)->first();
            if (!$network) return response()->json(['success'=>false,'message'=>'No active network'],422);
            $wallet = CryptoWallet::getOrCreate($user->id,$coin->id,$network->id);
            if ($wallet->balance < $request->amount) return response()->json(['success'=>false,'message'=>'Insufficient crypto balance'],422);
            $wallet->lock($request->amount);
        }

        $ad = P2pAd::create([
            'uuid'            => (string) Str::uuid(),
            'user_id'         => $user->id,
            'coin_id'         => $coin->id,
            'type'            => $request->type,
            'amount'          => $request->amount,
            'remaining'       => $request->amount,
            'price_usd'       => $request->price_usd,
            'payment_methods' => $request->payment_methods,
            'min_order_usd'   => $request->min_order_usd,
            'max_order_usd'   => $request->max_order_usd,
            'terms'           => $request->terms,
            'status'          => 'active',
        ]);

        return response()->json(['success'=>true,'message'=>'Advertisement created','data'=>$this->_formatAd($ad,$user)]);
    }

    public function myAds(Request $request)
    {
        $ads = P2pAd::with('coin')->where('user_id',$request->user()->id)->orderByDesc('created_at')->paginate(20);
        return response()->json(['success'=>true,'data'=>$ads->through(fn($ad) => $this->_formatAd($ad,$request->user()))]);
    }

    public function cancelAd(Request $request, string $uuid)
    {
        $ad = P2pAd::where('uuid',$uuid)->where('user_id',$request->user()->id)->firstOrFail();
        if ($ad->type === 'sell' && $ad->remaining > 0) {
            $network = ExchangeNetwork::where('coin_id',$ad->coin_id)->where('is_active',true)->first();
            if ($network) {
                $wallet = CryptoWallet::getOrCreate($request->user()->id,$ad->coin_id,$network->id);
                $wallet->unlock($ad->remaining);
            }
        }
        $ad->update(['status'=>'cancelled']);
        return response()->json(['success'=>true,'message'=>'Advertisement cancelled']);
    }

    // ── Orders ────────────────────────────────────────────────────────────────

    public function placeOrder(Request $request)
    {
        $request->validate([
            'ad_uuid'        => 'required|string',
            'crypto_amount'  => 'required|numeric|min:0',
            'payment_method' => 'required|string',
        ]);

        $user  = $request->user();
        $ad    = P2pAd::with('coin')->where('uuid',$request->ad_uuid)->where('status','active')->firstOrFail();
        if ($ad->user_id === $user->id) return response()->json(['success'=>false,'message'=>'Cannot order your own ad'],422);

        $amount    = (float) $request->crypto_amount;
        $totalUsd  = round($amount * $ad->price_usd, 4);

        if ($totalUsd < $ad->min_order_usd) return response()->json(['success'=>false,'message'=>"Minimum order is \${$ad->min_order_usd}"],422);
        if ($ad->max_order_usd > 0 && $totalUsd > $ad->max_order_usd) return response()->json(['success'=>false,'message'=>"Maximum order is \${$ad->max_order_usd}"],422);
        if ($amount > $ad->remaining) return response()->json(['success'=>false,'message'=>'Not enough crypto in this ad'],422);

        // Determine buyer/seller
        $buyerId  = $ad->type === 'sell' ? $user->id : $ad->user_id;
        $sellerId = $ad->type === 'sell' ? $ad->user_id : $user->id;

        $order = P2pOrder::create([
            'uuid'           => (string) Str::uuid(),
            'ad_id'          => $ad->id,
            'buyer_id'       => $buyerId,
            'seller_id'      => $sellerId,
            'coin_id'        => $ad->coin_id,
            'crypto_amount'  => $amount,
            'price_usd'      => $ad->price_usd,
            'total_usd'      => $totalUsd,
            'payment_method' => $request->payment_method,
            'status'         => 'payment_waiting',
            'expires_at'     => now()->addMinutes($ad->auto_reply_minutes + 15),
        ]);

        // Lock seller's crypto in escrow (for sell ads the crypto is already locked in ad)
        if ($ad->type === 'sell') {
            P2pEscrow::create([
                'order_id' => $order->id,
                'coin_id'  => $ad->coin_id,
                'amount'   => $amount,
                'status'   => 'locked',
            ]);
        }

        // Reduce ad remaining
        $ad->decrement('remaining', $amount);
        $ad->increment('total_orders');
        if ($ad->remaining <= 0) $ad->update(['status'=>'completed']);

        // Notify seller
        $seller = \App\Models\User::find($sellerId);
        try {
            if ($seller?->fcm_token) FcmService::sendToToken($seller->fcm_token,'New P2P Order',"New order for {$amount} {$ad->coin->symbol}.",[],null);
        } catch (\Throwable) {}

        // System message
        P2pMessage::create(['order_id'=>$order->id,'sender_id'=>$user->id,'message'=>'Order placed. Please make payment and click "I Paid".','is_system'=>true]);

        return response()->json(['success'=>true,'message'=>'Order placed successfully','data'=>$this->_formatOrder($order)]);
    }

    public function markPaid(Request $request, string $uuid)
    {
        $order = P2pOrder::where('uuid',$uuid)->where('buyer_id',$request->user()->id)->where('status','payment_waiting')->firstOrFail();
        $order->update(['status'=>'payment_sent','paid_at'=>now()]);

        P2pMessage::create(['order_id'=>$order->id,'sender_id'=>$request->user()->id,'message'=>'Payment sent. Waiting for seller to release crypto.','is_system'=>true]);

        $seller = \App\Models\User::find($order->seller_id);
        try {
            if ($seller?->fcm_token) FcmService::sendToToken($seller->fcm_token,'Payment Sent','Buyer marked payment as sent. Please verify and release crypto.',[],null);
        } catch (\Throwable) {}

        return response()->json(['success'=>true,'message'=>'Payment marked as sent']);
    }

    public function releaseCrypto(Request $request, string $uuid)
    {
        $user  = $request->user();
        $order = P2pOrder::with(['escrow','coin'])->where('uuid',$uuid)
            ->where('seller_id',$user->id)
            ->whereIn('status',['payment_sent','release_pending'])
            ->firstOrFail();

        $escrow = $order->escrow;
        if (!$escrow) return response()->json(['success'=>false,'message'=>'Escrow not found'],422);

        // Transfer crypto to buyer
        $network = ExchangeNetwork::where('coin_id',$order->coin_id)->where('is_active',true)->first();
        if ($network) {
            $buyerWallet = CryptoWallet::getOrCreate($order->buyer_id, $order->coin_id, $network->id);
            $buyerWallet->credit($escrow->amount,'escrow_release',"P2P order #{$order->uuid}",'p2p_order',$order->id);
        }

        $escrow->update(['status'=>'released','released_by'=>$user->id,'released_at'=>now()]);
        $order->update(['status'=>'released','released_at'=>now()]);
        P2pAd::where('id',$order->ad_id)->increment('completed_count');

        P2pMessage::create(['order_id'=>$order->id,'sender_id'=>$user->id,'message'=>'Crypto released! Order completed.','is_system'=>true]);

        $buyer = \App\Models\User::find($order->buyer_id);
        try {
            if ($buyer?->fcm_token) FcmService::sendToToken($buyer->fcm_token,'Crypto Released!',"{$escrow->amount} {$order->coin->symbol} has been sent to your wallet.",[],null);
        } catch (\Throwable) {}

        return response()->json(['success'=>true,'message'=>'Crypto released to buyer']);
    }

    public function cancelOrder(Request $request, string $uuid)
    {
        $user  = $request->user();
        $order = P2pOrder::where('uuid',$uuid)
            ->where(fn($q) => $q->where('buyer_id',$user->id)->orWhere('seller_id',$user->id))
            ->whereIn('status',['payment_waiting','pending'])
            ->firstOrFail();

        // Refund escrow back to seller's locked balance → unlock
        if ($order->escrow) {
            $network = ExchangeNetwork::where('coin_id',$order->coin_id)->where('is_active',true)->first();
            if ($network) {
                $sellerWallet = CryptoWallet::getOrCreate($order->seller_id,$order->coin_id,$network->id);
                $sellerWallet->unlock($order->escrow->amount);
            }
            $order->escrow->update(['status'=>'refunded','released_at'=>now()]);
        }

        $order->update(['status'=>'cancelled','cancelled_at'=>now(),'cancel_reason'=>$request->reason]);
        P2pAd::where('id',$order->ad_id)->increment('remaining', $order->crypto_amount);
        P2pAd::where('id',$order->ad_id)->update(['status'=>'active']);

        return response()->json(['success'=>true,'message'=>'Order cancelled']);
    }

    public function openDispute(Request $request, string $uuid)
    {
        $request->validate(['reason'=>'required|string|max:300']);
        $user  = $request->user();
        $order = P2pOrder::where('uuid',$uuid)
            ->where(fn($q) => $q->where('buyer_id',$user->id)->orWhere('seller_id',$user->id))
            ->whereIn('status',['payment_sent','release_pending','payment_waiting'])
            ->firstOrFail();

        if ($order->dispute) return response()->json(['success'=>false,'message'=>'Dispute already opened'],422);

        $order->update(['status'=>'disputed']);
        P2pDispute::create(['order_id'=>$order->id,'opened_by'=>$user->id,'reason'=>$request->reason,'status'=>'open']);
        P2pMessage::create(['order_id'=>$order->id,'sender_id'=>$user->id,'message'=>'Dispute opened: '.$request->reason,'is_system'=>true]);

        return response()->json(['success'=>true,'message'=>'Dispute opened. Admin will review.']);
    }

    public function orderMessages(Request $request, string $uuid)
    {
        $user  = $request->user();
        $order = P2pOrder::where('uuid',$uuid)
            ->where(fn($q)=>$q->where('buyer_id',$user->id)->orWhere('seller_id',$user->id))
            ->firstOrFail();

        $msgs = P2pMessage::with('sender')->where('order_id',$order->id)->orderBy('created_at')->get();
        return response()->json(['success'=>true,'data'=>$msgs]);
    }

    public function sendMessage(Request $request, string $uuid)
    {
        $request->validate(['message'=>'required_without:attachment|nullable|string|max:1000','attachment'=>'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120']);
        $user  = $request->user();
        $order = P2pOrder::where('uuid',$uuid)
            ->where(fn($q)=>$q->where('buyer_id',$user->id)->orWhere('seller_id',$user->id))
            ->whereNotIn('status',['released','cancelled','expired'])
            ->firstOrFail();

        $attachment = null;
        if ($request->hasFile('attachment')) {
            $attachment = $request->file('attachment')->store('p2p/attachments','public');
        }

        $msg = P2pMessage::create(['order_id'=>$order->id,'sender_id'=>$user->id,'message'=>$request->message,'attachment'=>$attachment]);
        return response()->json(['success'=>true,'data'=>$msg->load('sender')]);
    }

    public function myOrders(Request $request)
    {
        $user = $request->user();
        $orders = P2pOrder::with(['coin','buyer','seller'])
            ->where(fn($q)=>$q->where('buyer_id',$user->id)->orWhere('seller_id',$user->id))
            ->when($request->status, fn($q)=>$q->where('status',$request->status))
            ->orderByDesc('created_at')->paginate(20);
        return response()->json(['success'=>true,'data'=>$orders->through(fn($o)=>$this->_formatOrder($o))]);
    }

    public function orderDetail(Request $request, string $uuid)
    {
        $user  = $request->user();
        $order = P2pOrder::with(['coin','buyer','seller','escrow','dispute'])
            ->where('uuid',$uuid)
            ->where(fn($q)=>$q->where('buyer_id',$user->id)->orWhere('seller_id',$user->id))
            ->firstOrFail();
        return response()->json(['success'=>true,'data'=>$this->_formatOrder($order,true)]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function _formatAd(P2pAd $ad, $user): array
    {
        return [
            'uuid'            => $ad->uuid,
            'type'            => $ad->type,
            'coin'            => ['symbol'=>$ad->coin?->symbol,'name'=>$ad->coin?->name],
            'amount'          => $ad->amount,
            'remaining'       => $ad->remaining,
            'price_usd'       => $ad->price_usd,
            'payment_methods' => $ad->payment_methods,
            'min_order_usd'   => $ad->min_order_usd,
            'max_order_usd'   => $ad->max_order_usd,
            'terms'           => $ad->terms,
            'status'          => $ad->status,
            'completed_count' => $ad->completed_count,
            'total_orders'    => $ad->total_orders,
            'is_merchant'     => $ad->is_merchant,
            'user'            => ['name'=>$ad->user?->name,'id'=>$ad->user_id],
            'is_mine'         => $user?->id === $ad->user_id,
        ];
    }

    private function _formatOrder(P2pOrder $order, bool $detail = false): array
    {
        $r = [
            'uuid'           => $order->uuid,
            'coin'           => ['symbol'=>$order->coin?->symbol,'name'=>$order->coin?->name],
            'crypto_amount'  => $order->crypto_amount,
            'price_usd'      => $order->price_usd,
            'total_usd'      => $order->total_usd,
            'payment_method' => $order->payment_method,
            'status'         => $order->status,
            'buyer'          => ['name'=>$order->buyer?->name,'id'=>$order->buyer_id],
            'seller'         => ['name'=>$order->seller?->name,'id'=>$order->seller_id],
            'paid_at'        => $order->paid_at,
            'released_at'    => $order->released_at,
            'expires_at'     => $order->expires_at,
            'created_at'     => $order->created_at,
        ];
        if ($detail) {
            $r['payment_proof'] = $order->payment_proof;
            $r['cancel_reason'] = $order->cancel_reason;
            $r['has_dispute']   = $order->dispute !== null;
        }
        return $r;
    }
}
