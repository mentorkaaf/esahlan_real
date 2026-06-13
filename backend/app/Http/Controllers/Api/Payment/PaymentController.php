<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Services\WaafiPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Centralized payment controller used by all modules.
 * Flutter calls /payment/initiate before placing an order,
 * then polls /payment/status/{ref} until success/fail,
 * then places the order with payment_method=waafi and payment_reference={ref}.
 */
class PaymentController extends Controller
{
    public function __construct(private WaafiPayService $waafi) {}

    /**
     * POST /payment/initiate
     * Initiate a Waafi Pay payment. Returns a reference to poll.
     */
    public function initiate(Request $request)
    {
        $v = Validator::make($request->all(), [
            'amount'           => 'required|numeric|min:0.01',
            'phone'            => 'required|string|min:9',
            'type'             => 'required|in:order,topup,custom',
            'description'      => 'nullable|string|max:200',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()]);

        if (!$this->waafi->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'Payment gateway not configured. Please contact admin.']);
        }

        $user      = $request->user();
        $amount    = (float) $request->amount;
        $reference = 'WFI-' . strtoupper(Str::random(12));

        // Record pending payment transaction
        $ptxId = DB::table('payment_transactions')->insertGetId([
            'reference'        => $reference,
            'user_id'          => $user->id,
            'gateway'          => 'waafi',
            'amount'           => $amount,
            'currency'         => 'USD',
            'transaction_type' => $request->type,
            'phone'            => $request->phone,
            'status'           => 'pending',
            'metadata'         => json_encode(['description' => $request->description]),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // Call Waafi Pay
        $result = $this->waafi->initiatePayment(
            $request->phone,
            $amount,
            $reference,
            $request->description ?? 'eSahlan Payment'
        );

        // Update with gateway response
        DB::table('payment_transactions')->where('id', $ptxId)->update([
            'gateway_reference' => $result['gateway_reference'],
            'gateway_response'  => json_encode($result['raw']),
            'status'            => $result['status'],
            'updated_at'        => now(),
        ]);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'code'    => $result['response_code'],
            ]);
        }

        return response()->json([
            'success'   => true,
            'reference' => $reference,
            'status'    => $result['status'],   // 'pending' or 'success'
            'message'   => $result['message'],
        ]);
    }

    /**
     * GET /payment/status/{reference}
     * Poll payment status. Flutter polls this every 3 seconds.
     */
    public function status(Request $request, string $reference)
    {
        $ptx = DB::table('payment_transactions')
            ->where('reference', $reference)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$ptx) {
            return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
        }

        // Already resolved
        if (in_array($ptx->status, ['success', 'failed'])) {
            return response()->json([
                'success'   => true,
                'status'    => $ptx->status,
                'reference' => $reference,
            ]);
        }

        // Poll gateway for pending
        $result = $this->waafi->checkStatus($reference);

        if ($result['status'] !== $ptx->status) {
            DB::table('payment_transactions')->where('reference', $reference)->update([
                'status'           => $result['status'],
                'gateway_response' => json_encode($result['raw']),
                'updated_at'       => now(),
            ]);
        }

        // If success and it's a topup, credit wallet now
        if ($result['status'] === 'success' && $ptx->transaction_type === 'topup') {
            $wallet = Wallet::getOrCreateFor('App\\Models\\User', $ptx->user_id);
            $wallet->credit(
                (float) $ptx->amount,
                'Wallet top-up via Waafi Pay',
                'payment_transactions',
                $ptx->id,
                'waafi'
            );
            // Mark as done so we don't double-credit
            DB::table('payment_transactions')->where('reference', $reference)->update([
                'transaction_type' => 'topup_done',
                'updated_at'       => now(),
            ]);
        }

        return response()->json([
            'success'   => true,
            'status'    => $result['status'],
            'reference' => $reference,
            'message'   => $result['message'],
        ]);
    }
}
