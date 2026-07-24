<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\MobilePayAccount;
use Illuminate\Support\Facades\Cache;

class MobilePayController extends Controller
{
    // GET /mobile-pay/accounts
    public function accounts()
    {
        $accounts = Cache::remember('mobile_pay_accounts.active', 300, function () {
            return MobilePayAccount::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'account_number', 'ussd_template', 'instructions', 'icon']);
        });

        return response()->json(['success' => true, 'data' => $accounts]);
    }
}
