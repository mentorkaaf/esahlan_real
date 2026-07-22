<?php

namespace App\Services;

use App\Models\ExchangeCoin;
use App\Models\CryptoPrice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CryptoMarketService
{
    private const COINGECKO_BASE = 'https://api.coingecko.com/api/v3';
    private const CACHE_TTL      = 120;  // 2 min live price cache
    private const HISTORY_TTL    = 300;  // 5 min chart cache

    // ── Fetch live prices for all active coins from CoinGecko ─────────────────
    public static function refreshPrices(): int
    {
        $coins = ExchangeCoin::where('is_active', true)->whereNotNull('coingecko_id')->get();
        if ($coins->isEmpty()) return 0;

        $ids = $coins->pluck('coingecko_id')->filter()->join(',');

        try {
            $res = Http::timeout(15)->get(self::COINGECKO_BASE . '/coins/markets', [
                'vs_currency'           => 'usd',
                'ids'                   => $ids,
                'order'                 => 'market_cap_desc',
                'per_page'              => 100,
                'page'                  => 1,
                'sparkline'             => false,
                'price_change_percentage' => '24h,7d',
            ]);

            if (!$res->successful()) {
                Log::warning('[CryptoMarket] CoinGecko error: ' . $res->status());
                return 0;
            }

            $data  = $res->json();
            $index = collect($data)->keyBy('id');
            $count = 0;

            foreach ($coins as $coin) {
                $d = $index->get($coin->coingecko_id);
                if (!$d) continue;

                CryptoPrice::where('coin_id', $coin->id)->update([
                    'price_usd'  => $d['current_price'] ?? 0,
                    'change_24h' => $d['price_change_percentage_24h'] ?? 0,
                    'change_7d'  => $d['price_change_percentage_7d_in_currency'] ?? 0,
                    'volume_24h' => $d['total_volume'] ?? 0,
                    'market_cap' => $d['market_cap'] ?? 0,
                    'high_24h'   => $d['high_24h'] ?? 0,
                    'low_24h'    => $d['low_24h'] ?? 0,
                    'fetched_at' => now(),
                    'updated_at' => now(),
                ]);
                $count++;
            }
            Cache::forget('crypto_market_list');
            return $count;
        } catch (\Throwable $e) {
            Log::warning('[CryptoMarket] refreshPrices failed: ' . $e->getMessage());
            return 0;
        }
    }

    // ── Get all coins with prices (cached) ────────────────────────────────────
    public static function getMarketList(): array
    {
        return Cache::remember('crypto_market_list', self::CACHE_TTL, function () {
            return ExchangeCoin::with(['price','networks'])
                ->where('is_active', true)
                ->orderBy('display_order')
                ->get()
                ->map(fn($c) => self::formatCoin($c))
                ->values()
                ->toArray();
        });
    }

    // ── Get OHLC/sparkline data for a coin chart ───────────────────────────────
    public static function getCoinHistory(string $geckoId, string $interval = '1'): array
    {
        $days = match($interval) {
            '1h'  => '1',    '4h' => '1',   '1d'  => '1',
            '1w'  => '7',    '1m' => '30',  '1y'  => '365',
            default => '1',
        };
        $key = "crypto_chart_{$geckoId}_{$days}";
        return Cache::remember($key, self::HISTORY_TTL, function () use ($geckoId, $days) {
            try {
                $res = Http::timeout(15)->get(self::COINGECKO_BASE . "/coins/{$geckoId}/market_chart", [
                    'vs_currency' => 'usd',
                    'days'        => $days,
                ]);
                if (!$res->successful()) return [];
                $prices = $res->json('prices') ?? [];
                return array_map(fn($p) => ['t' => $p[0], 'p' => $p[1]], $prices);
            } catch (\Throwable) {
                return [];
            }
        });
    }

    public static function formatCoin(ExchangeCoin $coin): array
    {
        $p = $coin->price;
        $effectivePrice = $p?->effectivePrice() ?? 0;
        return [
            'id'               => $coin->id,
            'symbol'           => $coin->symbol,
            'name'             => $coin->name,
            'coingecko_id'     => $coin->coingecko_id,
            'logo_url'         => $coin->logo_url ?? "https://assets.coingecko.com/coins/images/1/small/{$coin->coingecko_id}.png",
            'price_usd'        => $effectivePrice,
            'change_24h'       => $p?->change_24h ?? 0,
            'change_7d'        => $p?->change_7d ?? 0,
            'volume_24h'       => $p?->volume_24h ?? 0,
            'market_cap'       => $p?->market_cap ?? 0,
            'high_24h'         => $p?->high_24h ?? 0,
            'low_24h'          => $p?->low_24h ?? 0,
            'decimals'         => $coin->decimals,
            'buy_enabled'      => $coin->buy_enabled,
            'sell_enabled'     => $coin->sell_enabled,
            'p2p_enabled'      => $coin->p2p_enabled,
            'deposit_enabled'  => $coin->deposit_enabled,
            'withdrawal_enabled'=> $coin->withdrawal_enabled,
            'buy_fee_pct'      => $coin->buy_fee_pct,
            'sell_fee_pct'     => $coin->sell_fee_pct,
            'min_withdrawal'   => $coin->min_withdrawal,
            'withdrawal_fee'   => $coin->withdrawal_fee,
            'networks'         => $coin->networks->where('is_active',true)->values()->toArray(),
        ];
    }
}
