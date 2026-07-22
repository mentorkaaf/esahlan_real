<?php

namespace App\Http\Controllers\Api\Crypto;

use App\Http\Controllers\Controller;
use App\Models\ExchangeCoin;
use App\Services\CryptoMarketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CryptoMarketController extends Controller
{
    // GET /api/v1/crypto/markets
    public function index(Request $request)
    {
        $coins = CryptoMarketService::getMarketList();

        if ($request->search) {
            $s = strtoupper($request->search);
            $coins = array_values(array_filter($coins, fn($c) =>
                str_contains(strtoupper($c['symbol']), $s) ||
                str_contains(strtoupper($c['name']), $s)
            ));
        }

        // Sort
        $sort = $request->sort ?? 'display_order';
        usort($coins, match($sort) {
            'gainers'  => fn($a,$b) => $b['change_24h'] <=> $a['change_24h'],
            'losers'   => fn($a,$b) => $a['change_24h'] <=> $b['change_24h'],
            'volume'   => fn($a,$b) => $b['volume_24h'] <=> $a['volume_24h'],
            'price'    => fn($a,$b) => $b['price_usd'] <=> $a['price_usd'],
            default    => fn($a,$b) => ($a['display_order'] ?? 99) <=> ($b['display_order'] ?? 99),
        });

        return response()->json(['success' => true, 'data' => array_values($coins)]);
    }

    // GET /api/v1/crypto/markets/{symbol}
    public function show(string $symbol)
    {
        $coin = ExchangeCoin::with(['price','networks'])
            ->where('symbol', strtoupper($symbol))
            ->where('is_active', true)
            ->first();

        if (!$coin) return response()->json(['success'=>false,'message'=>'Coin not found'],404);

        return response()->json(['success'=>true,'data'=> CryptoMarketService::formatCoin($coin)]);
    }

    // GET /api/v1/crypto/markets/{symbol}/chart?interval=1d
    public function chart(string $symbol, Request $request)
    {
        $coin = ExchangeCoin::where('symbol', strtoupper($symbol))->first();
        if (!$coin) return response()->json(['success'=>false,'message'=>'Coin not found'],404);

        $data = CryptoMarketService::getCoinHistory($coin->coingecko_id, $request->interval ?? '1d');
        return response()->json(['success'=>true,'data'=>$data]);
    }
}
