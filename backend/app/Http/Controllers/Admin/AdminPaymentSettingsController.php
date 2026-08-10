<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AppSettings;
use App\Http\Controllers\Controller;
use App\Models\MobilePayAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class AdminPaymentSettingsController extends Controller
{
    private array $methods = [
        'cod'    => ['label' => 'Cash on Delivery', 'icon' => 'fa-money-bill-wave', 'color' => '#FF8A00'],
        'waafi'  => ['label' => 'Waafi Pay',         'icon' => 'fa-credit-card',     'color' => '#7B1FA2'],
        'wallet' => ['label' => 'ePay Wallet',        'icon' => 'fa-wallet',          'color' => '#1565C0'],
        'mobile' => ['label' => 'Mobile Pay',         'icon' => 'fa-mobile-alt',      'color' => '#2E7D32'],
    ];

    public function index()
    {
        $statuses = [];
        foreach (array_keys($this->methods) as $key) {
            $statuses[$key] = (bool) AppSettings::get("payment_{$key}_enabled", true);
        }

        $waafiConfig = [
            'merchant_uid' => AppSettings::get('waafi_merchant_uid', ''),
            'api_user_id'  => AppSettings::get('waafi_api_user_id', ''),
            'api_key'      => AppSettings::get('waafi_api_key', ''),
            'api_url'      => AppSettings::get('waafi_api_url', 'https://api.waafipay.net/asm'),
            'description'  => AppSettings::get('waafi_description', 'eSahlan Payment'),
        ];

        $walletConfig = [
            'min_topup' => AppSettings::get('wallet_min_topup', 0.10),
            'max_topup' => AppSettings::get('wallet_max_topup', 1000),
        ];

        $mobileAccounts = MobilePayAccount::orderBy('sort_order')->get();

        return view('admin.payments.index', compact(
            'statuses', 'waafiConfig', 'walletConfig', 'mobileAccounts'
        ));
    }

    public function toggle(Request $request, string $method)
    {
        if (!array_key_exists($method, $this->methods)) {
            return response()->json(['error' => 'Unknown method'], 422);
        }

        $current = (bool) AppSettings::get("payment_{$method}_enabled", true);
        $new = !$current;
        AppSettings::set("payment_{$method}_enabled", $new ? '1' : '0', 'boolean');
        Cache::forget('payment_methods_enabled');

        return response()->json([
            'enabled' => $new,
            'label'   => $this->methods[$method]['label'],
        ]);
    }

    public function updateWaafi(Request $request)
    {
        $data = $request->validate([
            'merchant_uid' => 'required|string|max:100',
            'api_user_id'  => 'required|string|max:100',
            'api_key'      => 'required|string|max:200',
            'api_url'      => 'required|url|max:300',
            'description'  => 'nullable|string|max:200',
        ]);

        AppSettings::set('waafi_merchant_uid', $data['merchant_uid']);
        AppSettings::set('waafi_api_user_id',  $data['api_user_id']);
        AppSettings::set('waafi_api_key',      $data['api_key']);
        AppSettings::set('waafi_api_url',      $data['api_url']);
        AppSettings::set('waafi_description',  $data['description'] ?? 'eSahlan Payment');

        return back()->with('success', 'Waafi Pay configuration saved.');
    }

    public function updateWallet(Request $request)
    {
        $data = $request->validate([
            'min_topup' => 'required|numeric|min:0.01',
            'max_topup' => 'required|numeric|min:1',
        ]);

        AppSettings::set('wallet_min_topup', $data['min_topup'], 'decimal');
        AppSettings::set('wallet_max_topup', $data['max_topup'], 'decimal');
        Cache::forget('setting_wallet_min_topup');
        Cache::forget('setting_wallet_max_topup');

        return back()->with('success', 'ePay Wallet configuration saved.');
    }

    // Mobile Pay accounts (delegated)
    public function mobileStore(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'ussd_template'  => 'required|string|max:200',
            'instructions'   => 'nullable|string|max:1000',
            'icon'           => 'nullable|string|max:10',
            'logo'           => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
            'sort_order'     => 'integer|min:0',
        ]);
        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('mobile_pay_logos', 'public');
        }
        MobilePayAccount::create($data + ['is_active' => true]);
        Cache::forget('mobile_pay_accounts.active');
        return back()->with('success', 'Mobile Pay account added.');
    }

    public function mobileUpdate(Request $request, MobilePayAccount $account)
    {
        $data = $request->validate([
            'name'           => 'sometimes|string|max:100',
            'account_number' => 'sometimes|string|max:50',
            'ussd_template'  => 'sometimes|string|max:200',
            'instructions'   => 'nullable|string|max:1000',
            'icon'           => 'nullable|string|max:10',
            'logo'           => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
            'sort_order'     => 'sometimes|integer|min:0',
            'is_active'      => 'sometimes|boolean',
        ]);
        if ($request->hasFile('logo')) {
            if ($account->logo) Storage::disk('public')->delete($account->logo);
            $data['logo'] = $request->file('logo')->store('mobile_pay_logos', 'public');
        }
        $account->update($data);
        Cache::forget('mobile_pay_accounts.active');
        return back()->with('success', 'Account updated.');
    }

    public function mobileDestroy(MobilePayAccount $account)
    {
        $account->delete();
        Cache::forget('mobile_pay_accounts.active');
        return back()->with('success', 'Account deleted.');
    }

    public function mobileToggle(MobilePayAccount $account)
    {
        $account->update(['is_active' => !$account->is_active]);
        Cache::forget('mobile_pay_accounts.active');
        return back()->with('success', 'Status updated.');
    }

    /** API — returns enabled payment methods for Flutter */
    public static function enabledMethods(): array
    {
        return Cache::remember('payment_methods_enabled', 300, function () {
            $methods = [];
            if (AppSettings::get('payment_cod_enabled',    true)) $methods[] = 'cod';
            if (AppSettings::get('payment_waafi_enabled',  true)) $methods[] = 'waafi_pay';
            if (AppSettings::get('payment_wallet_enabled', true)) $methods[] = 'wallet';
            if (AppSettings::get('payment_mobile_enabled', true)) $methods[] = 'mobile_pay';
            return $methods;
        });
    }
}
