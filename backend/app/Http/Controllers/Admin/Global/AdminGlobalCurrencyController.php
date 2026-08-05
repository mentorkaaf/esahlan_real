<?php

namespace App\Http\Controllers\Admin\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class AdminGlobalCurrencyController extends Controller
{
    private array $supported = ['USD','EUR','GBP','CAD','AUD','NOK','SEK','DKK','CHF','JPY','SAR','AED'];

    public function index()
    {
        $base    = GlobalSetting::get('global_default_currency', 'USD');
        $rates   = json_decode(GlobalSetting::get('global_currency_rates', '{}'), true) ?: [];
        $lastSync = GlobalSetting::get('global_currency_last_sync', null);

        return view('admin.global.currency.index', compact('base', 'rates', 'lastSync'));
    }

    public function updateRate(Request $request)
    {
        $request->validate([
            'currency' => 'required|string|size:3',
            'rate'     => 'required|numeric|min:0.0001',
        ]);

        $rates = json_decode(GlobalSetting::get('global_currency_rates', '{}'), true) ?: [];
        $rates[strtoupper($request->currency)] = (float)$request->rate;
        GlobalSetting::set('global_currency_rates', json_encode($rates));

        return back()->with('success', "{$request->currency} rate updated to {$request->rate}.");
    }

    public function syncRates(Request $request)
    {
        // Free API — no key needed (exchangerate.host or similar)
        $base = GlobalSetting::get('global_default_currency', 'USD');

        try {
            $resp = Http::timeout(10)->get("https://open.er-api.com/v6/latest/{$base}");
            if ($resp->ok()) {
                $all   = $resp->json('rates', []);
                $rates = array_intersect_key($all, array_flip($this->supported));
                GlobalSetting::set('global_currency_rates', json_encode($rates));
                GlobalSetting::set('global_currency_last_sync', now()->toDateTimeString());
                return back()->with('success', 'Exchange rates synced successfully (' . count($rates) . ' currencies).');
            }
        } catch (\Throwable $e) {}

        return back()->with('error', 'Could not fetch live rates. Update manually below.');
    }
}
