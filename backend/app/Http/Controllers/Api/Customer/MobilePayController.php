<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\MobilePayAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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

    // POST /mobile-pay/attach-proof
    // Called after order creation to link proof_token to an order
    public function attachProof(Request $request)
    {
        $request->validate([
            'order_number' => 'required|string',
            'proof_token'  => 'required|string',
        ]);

        $order = \App\Models\Order::where('order_number', $request->order_number)
            ->where('payment_method', 'mobile_pay')
            ->firstOrFail();

        $meta = $order->meta ?? [];
        $meta['proof_token'] = $request->proof_token;
        $order->update(['meta' => $meta]);

        return response()->json(['success' => true]);
    }

    // POST /mobile-pay/submit-proof
    // Accepts: phone (string), image (file), account_id (int), amount (numeric)
    // Returns: { proof_token }
    public function submitProof(Request $request)
    {
        $request->validate([
            'phone'      => 'required|string|max:30',
            'account_id' => 'required|integer',
            'amount'     => 'required|numeric',
            'image'      => 'required|image|max:5120', // 5 MB
        ]);

        $path = $request->file('image')->store('mobile_pay_proofs', 'public');
        $token = Str::uuid()->toString();

        // Store proof in cache keyed by token (48h TTL is enough for order verification)
        Cache::put('mobile_pay_proof:' . $token, [
            'phone'      => $request->phone,
            'account_id' => $request->account_id,
            'amount'     => $request->amount,
            'image_path' => $path,
            'image_url'  => url('storage/' . $path),
            'created_at' => now()->toISOString(),
        ], 60 * 60 * 48);

        return response()->json(['success' => true, 'proof_token' => $token]);
    }
}
