<?php

namespace App\Http\Controllers\Admin\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalSetting;
use App\Models\Global\GlobalShippingZone;
use App\Events\RealtimeEvent;
use Illuminate\Http\Request;

class AdminGlobalSettingsController extends Controller
{
    public function index()
    {
        $keys = [
            'global_stripe_enabled','global_stripe_test_mode',
            'global_stripe_pk_live','global_stripe_sk_live',
            'global_stripe_pk_test','global_stripe_sk_test','global_stripe_webhook_secret',
            'global_paypal_enabled','global_paypal_test_mode',
            'global_paypal_client_id_live','global_paypal_secret_live',
            'global_paypal_client_id_test','global_paypal_secret_test',
            'global_store_name','global_store_email',
            'global_default_currency','global_supported_currencies',
            'global_tax_rate','global_free_shipping_over','global_maintenance_mode',
            'global_reviews_enabled','global_coupons_enabled','global_wishlist_enabled',
        ];

        $settings      = GlobalSetting::whereIn('key', $keys)->pluck('value', 'key');
        $shippingZones = GlobalShippingZone::orderBy('id')->get();

        return view('admin.global.settings', compact('settings', 'shippingZones'));
    }

    public function update(Request $request)
    {
        $allowed = [
            'global_stripe_enabled','global_stripe_test_mode',
            'global_stripe_pk_live','global_stripe_sk_live',
            'global_stripe_pk_test','global_stripe_sk_test','global_stripe_webhook_secret',
            'global_paypal_enabled','global_paypal_test_mode',
            'global_paypal_client_id_live','global_paypal_secret_live',
            'global_paypal_client_id_test','global_paypal_secret_test',
            'global_store_name','global_store_email',
            'global_default_currency','global_supported_currencies',
            'global_tax_rate','global_free_shipping_over','global_maintenance_mode',
            'global_reviews_enabled','global_coupons_enabled','global_wishlist_enabled',
        ];

        $booleans = [
            'global_stripe_enabled','global_stripe_test_mode',
            'global_paypal_enabled','global_paypal_test_mode',
            'global_maintenance_mode','global_reviews_enabled',
            'global_coupons_enabled','global_wishlist_enabled',
        ];

        foreach ($allowed as $key) {
            $value = in_array($key, $booleans)
                ? ($request->boolean($key) ? '1' : '0')
                : ($request->input($key) ?? '');
            GlobalSetting::set($key, $value);
        }

        return back()->with('success', 'Settings saved successfully.');
    }

    // ── Real-time toggle — called via AJAX from settings page ─────────────────
    public function toggleStore(Request $request)
    {
        $enabled = $request->boolean('enabled');
        GlobalSetting::set('global_store_enabled', $enabled ? '1' : '0');

        // Broadcast to all Flutter clients instantly via Reverb
        broadcast(new RealtimeEvent(
            channels: [['channel' => 'global.store', 'type' => 'public']],
            eventName: 'store.toggled',
            payload: [
                'enabled' => $enabled,
                'message' => $enabled
                    ? 'Global Store is now open'
                    : 'Global Store has been closed by admin',
            ],
        ));

        return response()->json([
            'success' => true,
            'enabled' => $enabled,
        ]);
    }

    // ── Real-time toggle — Crypto Exchange ───────────────────────────────────
    public function toggleCrypto(Request $request)
    {
        $enabled = $request->boolean('enabled');
        GlobalSetting::set('crypto_exchange_enabled', $enabled ? '1' : '0');

        broadcast(new RealtimeEvent(
            channels: [['channel' => 'global.exchange', 'type' => 'public']],
            eventName: 'crypto.toggled',
            payload: [
                'enabled' => $enabled,
                'message' => $enabled ? 'Crypto Exchange is now enabled' : 'Crypto Exchange has been disabled',
            ],
        ));

        return response()->json(['success' => true, 'enabled' => $enabled]);
    }

    public function updateShippingZone(Request $request, GlobalShippingZone $zone)
    {
        $data = $request->validate([
            'name'               => 'required|string',
            'countries'          => 'required|string',
            'flat_rate'          => 'required|numeric|min:0',
            'free_shipping_over' => 'nullable|numeric|min:0',
            'estimated_days_min' => 'required|integer|min:1',
            'estimated_days_max' => 'required|integer|min:1',
            'is_active'          => 'boolean',
        ]);

        $data['countries']  = array_map('trim', explode(',', $data['countries']));
        $data['is_active']  = $request->boolean('is_active', true);

        $zone->update($data);
        return back()->with('success', 'Shipping zone updated.');
    }
}
