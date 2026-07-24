<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MobilePayAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
            'sort_order'     => 'integer|min:0',
        ]);
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
            'sort_order'     => 'sometimes|integer|min:0',
            'is_active'      => 'sometimes|boolean',
        ]);
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
