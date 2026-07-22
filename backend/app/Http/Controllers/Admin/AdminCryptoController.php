<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ExchangeCoin, ExchangeNetwork, CryptoDeposit, CryptoWallet, CryptoWithdrawal, CryptoOrder, CryptoPrice, P2pAd, P2pOrder, P2pEscrow, P2pDispute};
use App\Services\CryptoMarketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCryptoController extends Controller
{
    // ── Dashboard ─────────────────────────────────────────────────────────────
    public function dashboard()
    {
        $today = now()->startOfDay();
        $month = now()->startOfMonth();

        $stats = [
            'total_holdings'    => DB::table('crypto_wallets')->sum(DB::raw('balance + locked_balance')),
            'total_users'       => DB::table('crypto_wallets')->distinct('user_id')->count('user_id'),
            'volume_24h'        => DB::table('crypto_orders')->where('status','completed')->where('created_at','>=',$today)->sum('total_usd'),
            'buy_volume'        => DB::table('crypto_orders')->where('status','completed')->where('side','buy')->where('created_at','>=',$today)->sum('total_usd'),
            'sell_volume'       => DB::table('crypto_orders')->where('status','completed')->where('side','sell')->where('created_at','>=',$today)->sum('total_usd'),
            'p2p_volume'        => DB::table('p2p_orders')->where('status','released')->where('created_at','>=',$today)->sum('total_usd'),
            'pending_wd'        => CryptoWithdrawal::where('status','pending')->count(),
            'pending_wd_amount' => CryptoWithdrawal::where('status','pending')->sum('amount'),
            'open_disputes'     => P2pDispute::where('status','open')->count(),
            'p2p_in_escrow'     => P2pEscrow::where('status','locked')->count(),
            'revenue_today'     => DB::table('crypto_orders')->where('status','completed')->where('created_at','>=',$today)->sum('fee_usd'),
            'revenue_month'     => DB::table('crypto_orders')->where('status','completed')->where('created_at','>=',$month)->sum('fee_usd'),
        ];

        $dailyVolume = DB::table('crypto_orders')
            ->where('status','completed')->where('created_at','>=',now()->subDays(7))
            ->selectRaw("DATE(created_at) as date, SUM(CASE WHEN side='buy' THEN total_usd ELSE 0 END) as buy_vol, SUM(CASE WHEN side='sell' THEN total_usd ELSE 0 END) as sell_vol")
            ->groupBy('date')->orderBy('date')->get();

        $topCoins = DB::table('crypto_orders')->join('exchange_coins','exchange_coins.id','=','crypto_orders.coin_id')
            ->where('crypto_orders.status','completed')->where('crypto_orders.created_at','>=',$month)
            ->selectRaw('exchange_coins.symbol, exchange_coins.name, SUM(total_usd) as volume, COUNT(*) as orders')
            ->groupBy('exchange_coins.id','exchange_coins.symbol','exchange_coins.name')
            ->orderByDesc('volume')->limit(8)->get();

        $recentTx = DB::table('crypto_orders')->join('users','users.id','=','crypto_orders.user_id')
            ->join('exchange_coins','exchange_coins.id','=','crypto_orders.coin_id')
            ->select('crypto_orders.uuid','users.name as user_name','exchange_coins.symbol','crypto_orders.side','crypto_orders.total_usd','crypto_orders.status','crypto_orders.created_at')
            ->orderByDesc('crypto_orders.created_at')->limit(10)->get();

        $coins = ExchangeCoin::with('price')->orderBy('display_order')->get();

        return view('admin.crypto.dashboard', compact('stats','dailyVolume','topCoins','recentTx','coins'));
    }

    // ── Coins ────────────────────────────────────────────────────────────────
    public function coins()
    {
        $coins = ExchangeCoin::with(['price','networks'])->orderBy('display_order')->get();
        return view('admin.crypto.coins', compact('coins'));
    }

    public function storeCoin(Request $request)
    {
        $request->validate(['symbol'=>'required|string|max:10','name'=>'required|string|max:60','coingecko_id'=>'required|string|max:60']);
        $coin = ExchangeCoin::create([
            'symbol'          => strtoupper($request->symbol),
            'name'            => $request->name,
            'coingecko_id'    => $request->coingecko_id,
            'buy_fee_pct'     => $request->buy_spread ?? 1.5,
            'sell_fee_pct'    => $request->sell_spread ?? 1.5,
            'min_withdrawal'  => $request->min_trade_amount ?? 5,
            'decimals'        => $request->decimal_places ?? 8,
            'is_active'       => $request->boolean('is_active'),
            'buy_enabled'     => $request->boolean('is_tradable'),
            'sell_enabled'    => $request->boolean('is_tradable'),
            'p2p_enabled'     => $request->boolean('is_p2p_enabled'),
            'deposit_enabled' => true,
            'withdrawal_enabled' => true,
        ]);
        // Parse networks_raw: "NAME,SYMBOL,FEE" per line
        foreach (explode("\n", $request->networks_raw ?? '') as $line) {
            $parts = array_map('trim', explode(',', $line));
            if (count($parts) >= 2 && $parts[0]) {
                ExchangeNetwork::create([
                    'coin_id'            => $coin->id,
                    'name'               => $parts[0],
                    'chain'              => $parts[1] ?? $parts[0],
                    'withdrawal_fee'     => $parts[2] ?? 1.0,
                    'is_active'          => true,
                    'deposit_enabled'    => true,
                    'withdrawal_enabled' => true,
                ]);
            }
        }
        CryptoMarketService::refreshPrices();
        return back()->with('success', "Coin {$coin->symbol} added.");
    }

    public function toggleCoin(int $id)
    {
        $coin = ExchangeCoin::findOrFail($id);
        $coin->update(['is_active'=>!$coin->is_active]);
        return back()->with('success', "{$coin->symbol} ".($coin->is_active?'enabled':'disabled').'.');
    }

    public function coinSettings(int $id)
    {
        $coin = ExchangeCoin::with(['price','networks'])->findOrFail($id);
        return view('admin.crypto.coin_settings', compact('coin'));
    }

    public function updateSpreads(Request $request)
    {
        foreach ($request->buy_spread ?? [] as $id => $buy) {
            ExchangeCoin::where('id',$id)->update([
                'buy_fee_pct'  => $buy,
                'sell_fee_pct' => $request->sell_spread[$id] ?? $buy,
            ]);
        }
        return back()->with('success','Spreads updated.');
    }

    public function overridePrice(Request $request)
    {
        $request->validate(['coin_id'=>'required','price'=>'required|numeric|min:0']);
        $cp = CryptoPrice::where('coin_id',$request->coin_id)->firstOrCreate(['coin_id'=>$request->coin_id],['price_usd'=>$request->price,'change_24h'=>0,'volume_24h'=>0,'market_cap'=>0,'high_24h'=>0,'low_24h'=>0]);
        $cp->update([
            'manual_price' => $request->price,
            'use_manual'   => true,
            'price_usd'    => $request->price,
        ]);
        \Illuminate\Support\Facades\Cache::forget('crypto_market_list');
        return back()->with('success','Price overridden for 1 hour.');
    }

    public function updateCoin(Request $request, int $id)
    {
        $coin = ExchangeCoin::findOrFail($id);

        // Add network sub-action
        if ($request->input('_action') === 'add_network') {
            ExchangeNetwork::create([
                'coin_id'        => $coin->id,
                'name'           => $request->net_name,
                'chain'          => $request->net_symbol ?? $request->net_name,
                'withdrawal_fee' => $request->net_fee ?? 1.0,
                'is_active'      => true,
                'deposit_enabled'    => true,
                'withdrawal_enabled' => true,
            ]);
            return back()->with('success', 'Network added.');
        }

        $coin->update([
            'name'               => $request->name ?? $coin->name,
            'coingecko_id'       => $request->coingecko_id ?? $coin->coingecko_id,
            'decimals'           => $request->decimals ?? $coin->decimals,
            'is_active'          => $request->boolean('is_active'),
            'buy_enabled'        => $request->boolean('buy_enabled'),
            'sell_enabled'       => $request->boolean('sell_enabled'),
            'p2p_enabled'        => $request->boolean('p2p_enabled'),
            'deposit_enabled'    => $request->boolean('deposit_enabled'),
            'withdrawal_enabled' => $request->boolean('withdrawal_enabled'),
            'buy_fee_pct'        => $request->buy_fee_pct ?? $coin->buy_fee_pct,
            'sell_fee_pct'       => $request->sell_fee_pct ?? $coin->sell_fee_pct,
            'min_deposit'        => $request->min_deposit ?? $coin->min_deposit,
            'min_withdrawal'     => $request->min_withdrawal ?? $coin->min_withdrawal,
            'max_withdrawal'     => $request->max_withdrawal ?? $coin->max_withdrawal,
            'withdrawal_fee'     => $request->withdrawal_fee ?? $coin->withdrawal_fee,
        ]);
        return back()->with('success', "Coin {$coin->symbol} updated.");
    }

    public function updatePrice(Request $request, int $id)
    {
        $cp = CryptoPrice::where('coin_id',$id)->firstOrFail();
        $cp->update([
            'use_manual'   => $request->boolean('use_manual'),
            'manual_price' => $request->manual_price,
            'spread_pct'   => $request->spread_pct ?? 0,
        ]);
        \Illuminate\Support\Facades\Cache::forget('crypto_market_list');
        return back()->with('success', 'Price settings updated.');
    }

    public function refreshPrices()
    {
        $count = CryptoMarketService::refreshPrices();
        return back()->with('success', "Refreshed prices for {$count} coins.");
    }

    // ── Withdrawals ───────────────────────────────────────────────────────────
    public function withdrawals(Request $request)
    {
        $q = CryptoWithdrawal::with(['user','coin','network'])
            ->when($request->status,  fn($q) => $q->where('status',$request->status))
            ->when($request->coin_id, fn($q) => $q->where('coin_id',$request->coin_id))
            ->when($request->q,       fn($q) => $q->whereHas('user',fn($u)=>$u->where('name','like','%'.$request->q.'%'))->orWhere('txhash','like','%'.$request->q.'%'))
            ->orderByDesc('created_at');

        $pendingCount = CryptoWithdrawal::where('status','pending')->count();
        $withdrawals  = $q->paginate(30)->withQueryString();
        $coins        = ExchangeCoin::where('is_active',true)->orderBy('display_order')->get();
        return view('admin.crypto.withdrawals', compact('withdrawals','pendingCount','coins'));
    }

    public function approveWithdrawal(Request $request, int $id)
    {
        $wd = CryptoWithdrawal::findOrFail($id);
        if ($wd->status !== 'pending') return back()->with('error','Not pending.');
        $wd->update(['status'=>'approved','approved_by'=>auth()->id(),'approved_at'=>now(),'admin_note'=>$request->note]);
        try {
            $u = $wd->user;
            if ($u?->fcm_token) \App\Services\FcmService::sendToToken($u->fcm_token,'Withdrawal Approved',"Your {$wd->coin?->symbol} withdrawal of {$wd->amount} has been approved.",[],null);
        } catch (\Throwable) {}
        return back()->with('success','Withdrawal approved.');
    }

    public function processWithdrawal(Request $request, int $id)
    {
        $request->validate(['txhash'=>'required|string|max:200']);
        $wd = CryptoWithdrawal::findOrFail($id);
        if (!in_array($wd->status,['approved','pending'])) return back()->with('error','Cannot process.');
        $wd->update(['status'=>'completed','txhash'=>$request->txhash,'processed_at'=>now(),'admin_note'=>$request->note]);
        try {
            $u = $wd->user;
            if ($u?->fcm_token) \App\Services\FcmService::sendToToken($u->fcm_token,'Withdrawal Processed',"Your {$wd->coin?->symbol} withdrawal has been sent. TXID: {$request->txhash}",[],null);
        } catch (\Throwable) {}
        return back()->with('success','Withdrawal processed.');
    }

    public function rejectWithdrawal(Request $request, int $id)
    {
        $wd = CryptoWithdrawal::findOrFail($id);
        if (!in_array($wd->status,['pending','approved'])) return back()->with('error','Cannot reject.');

        // Refund crypto to user wallet
        $network = ExchangeNetwork::where('coin_id',$wd->coin_id)->where('is_active',true)->first();
        if ($network) {
            $wallet = CryptoWallet::getOrCreate($wd->user_id, $wd->coin_id, $network->id);
            $wallet->credit($wd->amount,'adjustment',"Withdrawal rejected - refunded",'crypto_withdrawal',$wd->id);
        }
        $wd->update(['status'=>'rejected','admin_note'=>$request->note]);
        return back()->with('success','Withdrawal rejected and refunded.');
    }

    // ── Orders ────────────────────────────────────────────────────────────────
    public function orders(Request $request)
    {
        $q = CryptoOrder::with(['user','coin'])
            ->when($request->side,    fn($q)=>$q->where('side',$request->side))
            ->when($request->status,  fn($q)=>$q->where('status',$request->status))
            ->when($request->coin_id, fn($q)=>$q->where('coin_id',$request->coin_id))
            ->when($request->from,    fn($q)=>$q->whereDate('created_at','>=',$request->from))
            ->when($request->to,      fn($q)=>$q->whereDate('created_at','<=',$request->to))
            ->when($request->q,       fn($q)=>$q->where('uuid','like','%'.$request->q.'%')->orWhereHas('user',fn($u)=>$u->where('name','like','%'.$request->q.'%')))
            ->orderByDesc('created_at');

        $summary = CryptoOrder::where('status','completed')
            ->selectRaw('SUM(total_usd) as total_usd, SUM(CASE WHEN side="buy" THEN total_usd ELSE 0 END) as buy_usd, SUM(CASE WHEN side="sell" THEN total_usd ELSE 0 END) as sell_usd, SUM(fee_usd) as fees')
            ->first();

        $orders = $q->paginate(30)->withQueryString();
        $coins  = ExchangeCoin::where('is_active',true)->orderBy('display_order')->get();
        return view('admin.crypto.orders', compact('orders','coins','summary'));
    }

    // ── Order Actions ─────────────────────────────────────────────────────────
    public function completeOrder(Request $request, int $id)
    {
        $order = CryptoOrder::with(['coin', 'user'])->findOrFail($id);
        if ($order->status !== 'pending') {
            return back()->with('error', 'Only pending orders can be completed.');
        }

        if ($order->side === 'buy') {
            $network = ExchangeNetwork::where('coin_id', $order->coin_id)->where('is_active', true)->first();
            if (!$network) return back()->with('error', 'No active network found for this coin.');
            $wallet = CryptoWallet::getOrCreate($order->user_id, $order->coin_id, $network->id);
            $wallet->credit($order->crypto_amount, 'buy', 'Admin approved buy order', 'crypto_order', $order->id);
        } elseif ($order->side === 'sell') {
            $netUsd = $order->total_usd - ($order->fee_usd ?? 0);
            if ($order->payment_method === 'epay') {
                $ePayWallet = \App\Models\Wallet::getOrCreateFor('App\Models\User', $order->user_id);
                $ePayWallet->credit($netUsd, "Sell {$order->crypto_amount} {$order->coin->symbol} - Admin approved", 'crypto_order', $order->id, 'wallet');
            }
        }

        $order->update(['status' => 'completed', 'admin_note' => $request->note]);
        return back()->with('success', 'Order completed successfully.');
    }

    public function rejectOrder(Request $request, int $id)
    {
        $order = CryptoOrder::with(['coin', 'user'])->findOrFail($id);
        if ($order->status !== 'pending') {
            return back()->with('error', 'Only pending orders can be rejected.');
        }

        // Refund crypto for a pending SELL (was debited on order creation)
        if ($order->side === 'sell') {
            $network = ExchangeNetwork::where('coin_id', $order->coin_id)->where('is_active', true)->first();
            if ($network) {
                $wallet = CryptoWallet::getOrCreate($order->user_id, $order->coin_id, $network->id);
                $wallet->credit($order->crypto_amount, 'sell_refund', 'Sell order rejected — refund', 'crypto_order', $order->id);
            }
        }
        // Refund USD for a pending ePay BUY (was debited on order creation)
        elseif ($order->side === 'buy' && $order->payment_method === 'epay') {
            $ePayWallet = \App\Models\Wallet::getOrCreateFor('App\Models\User', $order->user_id);
            $ePayWallet->credit($order->total_usd, 'Buy order rejected — refund', 'crypto_order', $order->id, 'wallet');
        }

        $order->update(['status' => 'failed', 'admin_note' => $request->note]);
        return back()->with('success', 'Order rejected.');
    }

    // ── P2P & Escrow ────────────────────────────────────────────────────────
    public function p2p(Request $request)
    {
        $tab = $request->get('tab', 'orders');
        $disputeCount = P2pDispute::where('status','open')->count();

        $orders   = collect(); $ads = collect(); $disputes = collect(); $escrows = collect();

        if ($tab === 'orders') {
            $orders = P2pOrder::with(['buyer','seller','ad.coin','escrow','dispute'])
                ->when($request->status, fn($q)=>$q->where('status',$request->status))
                ->when($request->q, fn($q)=>$q->where('uuid','like','%'.$request->q.'%'))
                ->orderByDesc('created_at')->paginate(30)->withQueryString();
        } elseif ($tab === 'ads') {
            $ads = P2pAd::with(['user','coin'])
                ->orderByDesc('created_at')->paginate(30)->withQueryString();
        } elseif ($tab === 'disputes') {
            $disputes = P2pDispute::with(['opener','order.ad.coin','order.seller','order.buyer'])
                ->where('status','open')->orderByDesc('created_at')->get();
        } elseif ($tab === 'escrow') {
            $escrows = P2pEscrow::with(['order.seller','order.ad.coin'])
                ->where('status','locked')->orderByDesc('created_at')->get();
        }

        return view('admin.crypto.p2p', compact('orders','ads','disputes','escrows','disputeCount'));
    }

    public function p2pResolve(Request $request, int $orderId)
    {
        $winner = $request->input('winner'); // 'buyer' or 'seller'
        if ($winner === 'buyer') return $this->escrowRelease($request, $orderId);
        return $this->escrowRefund($request, $orderId);
    }

    public function disableAd(int $id)
    {
        P2pAd::findOrFail($id)->update(['status'=>'cancelled']);
        return back()->with('success','Ad disabled.');
    }

    public function escrowRelease(Request $request, int $orderId)
    {
        $order  = P2pOrder::with(['escrow','coin'])->findOrFail($orderId);
        $escrow = $order->escrow;
        if (!$escrow || $escrow->status !== 'locked') return back()->with('error','Escrow not locked.');

        $network = ExchangeNetwork::where('coin_id',$order->coin_id)->where('is_active',true)->first();
        if ($network) {
            $buyerWallet = CryptoWallet::getOrCreate($order->buyer_id,$order->coin_id,$network->id);
            $buyerWallet->credit($escrow->amount,'escrow_release',"Admin P2P release #{$order->uuid}",'p2p_order',$order->id);
        }
        $escrow->update(['status'=>'released','released_by'=>auth()->id(),'released_at'=>now()]);
        $order->update(['status'=>'released','released_at'=>now()]);

        // Resolve dispute if any
        if ($order->dispute) {
            $order->dispute->update(['status'=>'resolved','resolved_by'=>auth()->id(),'resolution'=>'release','resolved_at'=>now(),'admin_note'=>$request->note]);
        }
        return back()->with('success','Crypto released to buyer.');
    }

    public function escrowRefund(Request $request, int $orderId)
    {
        $order  = P2pOrder::with(['escrow','coin'])->findOrFail($orderId);
        $escrow = $order->escrow;
        if (!$escrow || $escrow->status !== 'locked') return back()->with('error','Escrow not locked.');

        $network = ExchangeNetwork::where('coin_id',$order->coin_id)->where('is_active',true)->first();
        if ($network) {
            $sellerWallet = CryptoWallet::getOrCreate($order->seller_id,$order->coin_id,$network->id);
            $sellerWallet->unlock($escrow->amount);
        }
        $escrow->update(['status'=>'refunded','released_by'=>auth()->id(),'released_at'=>now()]);
        $order->update(['status'=>'cancelled','cancelled_at'=>now(),'cancel_reason'=>'Admin refund: '.($request->note??'')]);

        if ($order->dispute) {
            $order->dispute->update(['status'=>'resolved','resolved_by'=>auth()->id(),'resolution'=>'refund','resolved_at'=>now(),'admin_note'=>$request->note]);
        }
        return back()->with('success','Crypto refunded to seller.');
    }

    // ── Deposits ─────────────────────────────────────────────────────────────
    public function deposits(Request $request)
    {
        $q = CryptoDeposit::with(['user','coin','network'])
            ->when($request->status,  fn($q)=>$q->where('status',$request->status))
            ->when($request->coin_id, fn($q)=>$q->where('coin_id',$request->coin_id))
            ->when($request->q,       fn($q)=>$q->where('txhash','like','%'.$request->q.'%')->orWhereHas('user',fn($u)=>$u->where('name','like','%'.$request->q.'%')))
            ->orderByDesc('created_at');
        $deposits = $q->paginate(30)->withQueryString();
        $coins    = ExchangeCoin::where('is_active',true)->orderBy('display_order')->get();
        return view('admin.crypto.deposits', compact('deposits','coins'));
    }

    public function rejectDeposit(Request $request, int $id)
    {
        $dep = CryptoDeposit::findOrFail($id);
        $dep->update(['status'=>'rejected','admin_note'=>$request->admin_note]);
        return back()->with('success','Deposit rejected.');
    }

    public function approveDeposit(Request $request, int $id)
    {
        $dep = \App\Models\CryptoDeposit::findOrFail($id);
        if ($dep->status === 'completed') return back()->with('error','Already completed.');

        $network = ExchangeNetwork::where('coin_id',$dep->coin_id)->where('is_active',true)->first();
        if ($network) {
            $wallet = CryptoWallet::getOrCreate($dep->user_id,$dep->coin_id,$network->id);
            $wallet->credit($dep->amount,'deposit',"Manual deposit credit",'crypto_deposit',$dep->id);
        }
        $dep->update(['status'=>'completed','confirmed_at'=>now(),'admin_note'=>$request->note,'txhash'=>$request->txhash??$dep->txhash]);

        $u = $dep->user;
        try {
            if ($u?->fcm_token) \App\Services\FcmService::sendToToken($u->fcm_token,'Deposit Confirmed',"Your {$dep->amount} {$dep->coin?->symbol} deposit has been credited.",[],null);
        } catch (\Throwable) {}
        return back()->with('success','Deposit approved and credited.');
    }

    // ── Settings ─────────────────────────────────────────────────────────────
    public function settings()
    {
        $settings = \Illuminate\Support\Facades\Cache::get('crypto_global_settings', [
            'min_buy_usd'          => 1,
            'max_buy_usd'          => 10000,
            'daily_buy_limit'      => 50000,
            'daily_withdraw_limit' => 10000,
            'p2p_fee_pct'          => 0.5,
            'p2p_order_expiry_mins'=> 30,
            'p2p_min_order_usd'    => 5,
            'p2p_max_order_usd'    => 5000,
            'trading_enabled'      => true,
            'p2p_enabled'          => true,
            'withdrawals_enabled'  => true,
            'default_buy_fee'      => 0.5,
            'default_sell_fee'     => 0.5,
        ]);
        return view('admin.crypto.settings', compact('settings'));
    }

    public function updateSettings(\Illuminate\Http\Request $request)
    {
        $data = $request->only([
            'min_buy_usd','max_buy_usd','daily_buy_limit','daily_withdraw_limit',
            'p2p_fee_pct','p2p_order_expiry_mins','p2p_min_order_usd','p2p_max_order_usd',
            'default_buy_fee','default_sell_fee',
        ]);
        $data['trading_enabled']     = $request->boolean('trading_enabled');
        $data['p2p_enabled']         = $request->boolean('p2p_enabled');
        $data['withdrawals_enabled'] = $request->boolean('withdrawals_enabled');

        \Illuminate\Support\Facades\Cache::put('crypto_global_settings', $data, now()->addYears(10));
        return back()->with('success', 'Settings saved successfully.');
    }
}
