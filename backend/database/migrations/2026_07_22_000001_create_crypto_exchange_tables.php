<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Coins ──────────────────────────────────────────────────────────────
        Schema::create('exchange_coins', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20)->unique();          // BTC, ETH, USDT
            $table->string('name', 100);                     // Bitcoin
            $table->string('coingecko_id', 100)->nullable(); // bitcoin
            $table->string('logo_url', 500)->nullable();
            $table->unsignedTinyInteger('decimals')->default(8);
            $table->boolean('deposit_enabled')->default(true);
            $table->boolean('withdrawal_enabled')->default(true);
            $table->boolean('buy_enabled')->default(true);
            $table->boolean('sell_enabled')->default(true);
            $table->boolean('p2p_enabled')->default(true);
            $table->boolean('is_active')->default(true);
            $table->decimal('min_deposit', 28, 8)->default(0);
            $table->decimal('min_withdrawal', 28, 8)->default(0);
            $table->decimal('max_withdrawal', 28, 8)->default(0);
            $table->decimal('withdrawal_fee', 28, 8)->default(0);
            $table->decimal('buy_fee_pct', 8, 4)->default(0.8);
            $table->decimal('sell_fee_pct', 8, 4)->default(0.8);
            $table->unsignedTinyInteger('display_order')->default(99);
            $table->timestamps();
        });

        // ── Networks ───────────────────────────────────────────────────────────
        Schema::create('exchange_networks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coin_id')->constrained('exchange_coins')->cascadeOnDelete();
            $table->string('name', 50);           // TRC20, ERC20, BEP20
            $table->string('chain', 30);          // TRON, ETH, BSC
            $table->string('contract_address', 200)->nullable();
            $table->unsignedTinyInteger('confirmations_required')->default(12);
            $table->decimal('withdrawal_fee', 28, 8)->default(0);
            $table->boolean('deposit_enabled')->default(true);
            $table->boolean('withdrawal_enabled')->default(true);
            $table->boolean('is_maintenance')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Per-user crypto wallets ────────────────────────────────────────────
        Schema::create('crypto_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coin_id')->constrained('exchange_coins')->cascadeOnDelete();
            $table->foreignId('network_id')->constrained('exchange_networks')->cascadeOnDelete();
            $table->string('address', 200);
            $table->decimal('balance', 28, 8)->default(0);
            $table->decimal('pending_balance', 28, 8)->default(0);
            $table->decimal('locked_balance', 28, 8)->default(0);  // in escrow
            $table->decimal('total_deposited', 28, 8)->default(0);
            $table->decimal('total_withdrawn', 28, 8)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'coin_id', 'network_id']);
            $table->index('address');
        });

        // ── Price cache ───────────────────────────────────────────────────────
        Schema::create('crypto_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coin_id')->constrained('exchange_coins')->cascadeOnDelete();
            $table->decimal('price_usd', 20, 8);
            $table->decimal('change_24h', 10, 4)->default(0);   // %
            $table->decimal('change_7d', 10, 4)->default(0);    // %
            $table->decimal('volume_24h', 28, 2)->default(0);
            $table->decimal('market_cap', 28, 2)->default(0);
            $table->decimal('high_24h', 20, 8)->default(0);
            $table->decimal('low_24h', 20, 8)->default(0);
            $table->decimal('spread_pct', 8, 4)->default(0);    // admin markup
            $table->decimal('manual_price', 20, 8)->nullable(); // override
            $table->boolean('use_manual')->default(false);
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
            $table->unique('coin_id');
        });

        // ── Deposits ──────────────────────────────────────────────────────────
        Schema::create('crypto_deposits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coin_id')->constrained('exchange_coins');
            $table->foreignId('network_id')->constrained('exchange_networks');
            $table->foreignId('wallet_id')->constrained('crypto_wallets');
            $table->string('txhash', 200)->nullable()->index();
            $table->decimal('amount', 28, 8);
            $table->decimal('fee', 28, 8)->default(0);
            $table->unsignedSmallInteger('confirmations')->default(0);
            $table->unsignedSmallInteger('required_confirmations')->default(12);
            $table->enum('status', ['pending','confirming','completed','failed'])->default('pending');
            $table->timestamp('confirmed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });

        // ── Withdrawals ───────────────────────────────────────────────────────
        Schema::create('crypto_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coin_id')->constrained('exchange_coins');
            $table->foreignId('network_id')->constrained('exchange_networks');
            $table->string('to_address', 200);
            $table->decimal('amount', 28, 8);
            $table->decimal('fee', 28, 8)->default(0);
            $table->decimal('net_amount', 28, 8);
            $table->string('txhash', 200)->nullable();
            $table->enum('status', ['pending','approved','processing','completed','rejected','cancelled'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });

        // ── Buy/Sell orders ───────────────────────────────────────────────────
        Schema::create('crypto_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coin_id')->constrained('exchange_coins');
            $table->enum('side', ['buy', 'sell']);
            $table->decimal('crypto_amount', 28, 8);
            $table->decimal('price_usd', 20, 8);            // price at time of order
            $table->decimal('total_usd', 20, 4);
            $table->decimal('fee_usd', 20, 4)->default(0);
            $table->decimal('fee_pct', 8, 4)->default(0);
            $table->string('payment_method', 50)->default('epay'); // epay, waafi_pay, evc, edahab
            $table->string('payment_reference', 200)->nullable();
            $table->enum('status', ['pending','completed','failed','cancelled'])->default('pending');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['user_id','status']);
            $table->index(['coin_id','side','status']);
        });

        // ── P2P Advertisements ────────────────────────────────────────────────
        Schema::create('p2p_ads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coin_id')->constrained('exchange_coins');
            $table->enum('type', ['buy', 'sell']);           // ad owner wants to buy/sell
            $table->decimal('amount', 28, 8);                // total crypto available
            $table->decimal('remaining', 28, 8);             // available
            $table->decimal('price_usd', 20, 4);             // per 1 coin
            $table->json('payment_methods');                  // ['evc','edahab','waafi_pay']
            $table->decimal('min_order_usd', 20, 4)->default(1);
            $table->decimal('max_order_usd', 20, 4)->default(0);
            $table->text('terms')->nullable();
            $table->unsignedSmallInteger('auto_reply_minutes')->default(15);
            $table->enum('status', ['active','paused','completed','cancelled'])->default('active');
            $table->unsignedInteger('completed_count')->default(0);
            $table->unsignedInteger('total_orders')->default(0);
            $table->boolean('is_merchant')->default(false);
            $table->timestamps();
            $table->index(['coin_id','type','status']);
        });

        // ── P2P Orders ────────────────────────────────────────────────────────
        Schema::create('p2p_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ad_id')->constrained('p2p_ads');
            $table->foreignId('buyer_id')->constrained('users');
            $table->foreignId('seller_id')->constrained('users');
            $table->foreignId('coin_id')->constrained('exchange_coins');
            $table->decimal('crypto_amount', 28, 8);
            $table->decimal('price_usd', 20, 4);
            $table->decimal('total_usd', 20, 4);
            $table->string('payment_method', 50);
            $table->string('payment_proof', 500)->nullable();    // uploaded screenshot
            $table->enum('status', [
                'pending','payment_waiting','payment_sent',
                'release_pending','released','cancelled','expired','disputed'
            ])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
            $table->index(['buyer_id','status']);
            $table->index(['seller_id','status']);
        });

        // ── P2P Escrow ────────────────────────────────────────────────────────
        Schema::create('p2p_escrow', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('p2p_orders')->cascadeOnDelete();
            $table->foreignId('coin_id')->constrained('exchange_coins');
            $table->decimal('amount', 28, 8);
            $table->enum('status', ['locked','released','refunded'])->default('locked');
            $table->foreignId('released_by')->nullable()->constrained('users');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });

        // ── P2P Messages ─────────────────────────────────────────────────────
        Schema::create('p2p_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('p2p_orders')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->string('attachment', 500)->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->index(['order_id','created_at']);
        });

        // ── P2P Disputes ─────────────────────────────────────────────────────
        Schema::create('p2p_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('p2p_orders')->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users');
            $table->string('reason', 300);
            $table->json('evidence')->nullable();   // array of file paths
            $table->enum('status', ['open','under_review','resolved'])->default('open');
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->enum('resolution', ['release','refund','split'])->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        // ── Crypto transaction ledger ────────────────────────────────────────
        Schema::create('crypto_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coin_id')->constrained('exchange_coins');
            $table->foreignId('wallet_id')->constrained('crypto_wallets');
            $table->enum('type', ['deposit','withdrawal','buy','sell','transfer_in','transfer_out','escrow_lock','escrow_release','adjustment']);
            $table->decimal('amount', 28, 8);
            $table->decimal('fee', 28, 8)->default(0);
            $table->decimal('balance_before', 28, 8);
            $table->decimal('balance_after', 28, 8);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('note')->nullable();
            $table->enum('status', ['pending','completed','failed'])->default('completed');
            $table->timestamps();
            $table->index(['user_id','coin_id','type']);
        });

        // ── Seed default coins ────────────────────────────────────────────────
        $now = now();
        $coins = [
            ['symbol'=>'USDT','name'=>'Tether USD',   'coingecko_id'=>'tether',       'decimals'=>6,  'display_order'=>1,'buy_fee_pct'=>0.5,'sell_fee_pct'=>0.5,'min_withdrawal'=>10,'withdrawal_fee'=>1],
            ['symbol'=>'BTC', 'name'=>'Bitcoin',      'coingecko_id'=>'bitcoin',       'decimals'=>8,  'display_order'=>2,'buy_fee_pct'=>0.8,'sell_fee_pct'=>0.8,'min_withdrawal'=>0.001,'withdrawal_fee'=>0.0001],
            ['symbol'=>'ETH', 'name'=>'Ethereum',     'coingecko_id'=>'ethereum',      'decimals'=>8,  'display_order'=>3,'buy_fee_pct'=>0.8,'sell_fee_pct'=>0.8,'min_withdrawal'=>0.01,'withdrawal_fee'=>0.002],
            ['symbol'=>'BNB', 'name'=>'BNB',          'coingecko_id'=>'binancecoin',   'decimals'=>8,  'display_order'=>4,'buy_fee_pct'=>0.8,'sell_fee_pct'=>0.8,'min_withdrawal'=>0.05,'withdrawal_fee'=>0.001],
            ['symbol'=>'SOL', 'name'=>'Solana',       'coingecko_id'=>'solana',        'decimals'=>8,  'display_order'=>5,'buy_fee_pct'=>0.8,'sell_fee_pct'=>0.8,'min_withdrawal'=>0.1, 'withdrawal_fee'=>0.01],
            ['symbol'=>'XRP', 'name'=>'XRP',          'coingecko_id'=>'ripple',        'decimals'=>6,  'display_order'=>6,'buy_fee_pct'=>0.8,'sell_fee_pct'=>0.8,'min_withdrawal'=>20,  'withdrawal_fee'=>0.25],
            ['symbol'=>'DOGE','name'=>'Dogecoin',     'coingecko_id'=>'dogecoin',      'decimals'=>8,  'display_order'=>7,'buy_fee_pct'=>0.8,'sell_fee_pct'=>0.8,'min_withdrawal'=>100, 'withdrawal_fee'=>5],
            ['symbol'=>'ADA', 'name'=>'Cardano',      'coingecko_id'=>'cardano',       'decimals'=>6,  'display_order'=>8,'buy_fee_pct'=>0.8,'sell_fee_pct'=>0.8,'min_withdrawal'=>10,  'withdrawal_fee'=>1],
        ];
        foreach ($coins as $c) {
            $coinId = DB::table('exchange_coins')->insertGetId(array_merge($c, [
                'is_active'=>true,'deposit_enabled'=>true,'withdrawal_enabled'=>true,
                'buy_enabled'=>true,'sell_enabled'=>true,'p2p_enabled'=>true,
                'max_withdrawal'=>0,'min_deposit'=>0,
                'logo_url'=>null,'created_at'=>$now,'updated_at'=>$now,
            ]));
            // Default network per coin
            $networkMap = [
                'USDT'=>['name'=>'TRC20','chain'=>'TRON','confirmations_required'=>20,'withdrawal_fee'=>1],
                'BTC' =>['name'=>'Bitcoin','chain'=>'BTC','confirmations_required'=>3,'withdrawal_fee'=>0.0001],
                'ETH' =>['name'=>'ERC20','chain'=>'ETH','confirmations_required'=>12,'withdrawal_fee'=>0.002],
                'BNB' =>['name'=>'BEP20','chain'=>'BSC','confirmations_required'=>15,'withdrawal_fee'=>0.001],
                'SOL' =>['name'=>'Solana','chain'=>'SOL','confirmations_required'=>32,'withdrawal_fee'=>0.01],
                'XRP' =>['name'=>'Ripple','chain'=>'XRP','confirmations_required'=>1,'withdrawal_fee'=>0.25],
                'DOGE'=>['name'=>'Dogecoin','chain'=>'DOGE','confirmations_required'=>6,'withdrawal_fee'=>5],
                'ADA' =>['name'=>'Cardano','chain'=>'ADA','confirmations_required'=>15,'withdrawal_fee'=>1],
            ];
            if (isset($networkMap[$c['symbol']])) {
                $n = $networkMap[$c['symbol']];
                DB::table('exchange_networks')->insert([
                    'coin_id'=>$coinId,'name'=>$n['name'],'chain'=>$n['chain'],
                    'confirmations_required'=>$n['confirmations_required'],
                    'withdrawal_fee'=>$n['withdrawal_fee'],
                    'deposit_enabled'=>true,'withdrawal_enabled'=>true,
                    'is_maintenance'=>false,'is_active'=>true,
                    'contract_address'=>null,
                    'created_at'=>$now,'updated_at'=>$now,
                ]);
            }
            // Seed price placeholder
            DB::table('crypto_prices')->insert([
                'coin_id'=>$coinId,'price_usd'=>0,'change_24h'=>0,'change_7d'=>0,
                'volume_24h'=>0,'market_cap'=>0,'high_24h'=>0,'low_24h'=>0,
                'spread_pct'=>0,'manual_price'=>null,'use_manual'=>false,
                'fetched_at'=>null,'created_at'=>$now,'updated_at'=>$now,
            ]);
        }
        // Add second USDT network (ERC20, BEP20)
        $usdtId = DB::table('exchange_coins')->where('symbol','USDT')->value('id');
        DB::table('exchange_networks')->insert([
            ['coin_id'=>$usdtId,'name'=>'ERC20','chain'=>'ETH','confirmations_required'=>12,'withdrawal_fee'=>5,'deposit_enabled'=>true,'withdrawal_enabled'=>true,'is_maintenance'=>false,'is_active'=>true,'contract_address'=>'0xdac17f958d2ee523a2206206994597c13d831ec7','created_at'=>$now,'updated_at'=>$now],
            ['coin_id'=>$usdtId,'name'=>'BEP20','chain'=>'BSC','confirmations_required'=>15,'withdrawal_fee'=>1,'deposit_enabled'=>true,'withdrawal_enabled'=>true,'is_maintenance'=>false,'is_active'=>true,'contract_address'=>'0x55d398326f99059fF775485246999027B3197955','created_at'=>$now,'updated_at'=>$now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_transactions');
        Schema::dropIfExists('p2p_disputes');
        Schema::dropIfExists('p2p_messages');
        Schema::dropIfExists('p2p_escrow');
        Schema::dropIfExists('p2p_orders');
        Schema::dropIfExists('p2p_ads');
        Schema::dropIfExists('crypto_orders');
        Schema::dropIfExists('crypto_withdrawals');
        Schema::dropIfExists('crypto_deposits');
        Schema::dropIfExists('crypto_prices');
        Schema::dropIfExists('crypto_wallets');
        Schema::dropIfExists('exchange_networks');
        Schema::dropIfExists('exchange_coins');
    }
};
