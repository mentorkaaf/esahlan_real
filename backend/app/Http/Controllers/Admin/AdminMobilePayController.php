<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MobilePayAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class AdminMobilePayController extends Controller
{
    public function index()
    {
        $accounts = MobilePayAccount::orderBy('sort_order')->get();
        return view('admin.mobile_pay.index', compact('accounts'));
    }

    public function store(Request $request)
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
        return back()->with('success', 'Account added.');
    }

    public function update(Request $request, MobilePayAccount $account)
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
            // Delete old logo if exists
            if ($account->logo) Storage::disk('public')->delete($account->logo);
            $data['logo'] = $request->file('logo')->store('mobile_pay_logos', 'public');
        }

        $account->update($data);
        Cache::forget('mobile_pay_accounts.active');
        return back()->with('success', 'Account updated.');
    }

    public function destroy(MobilePayAccount $account)
    {
        $account->delete();
        Cache::forget('mobile_pay_accounts.active');
        return back()->with('success', 'Account deleted.');
    }

    public function toggleStatus(MobilePayAccount $account)
    {
        $account->update(['is_active' => !$account->is_active]);
        Cache::forget('mobile_pay_accounts.active');
        return back()->with('success', 'Status updated.');
    }
}
