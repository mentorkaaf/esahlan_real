<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Services\ReferralService;
use App\Services\WaafiPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public function __construct(private WaafiPayService $waafi) {}

    // GET /wallet
    public function index(Request $request)
    {
        $user   = $request->user();
        $wallet = $user->wallet;

        // Check for pending mobile pay top-up request
        $pendingTopup = null;
        if ($wallet) {
            $pr = DB::table('wallet_topup_requests')
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->first(['id', 'amount', 'created_at']);
            if ($pr) {
                $pendingTopup = ['id' => $pr->id, 'amount' => (float)$pr->amount, 'created_at' => $pr->created_at];
            }
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'balance'        => (float) ($wallet?->balance ?? 0),
                'currency'       => $wallet?->currency ?? 'USD',
                'loyalty_points' => (int) ($user->points_balance ?? $user->loyalty_points ?? 0),
                'has_wallet'     => (bool) $wallet,
                'is_frozen'       => (bool) ($wallet?->is_frozen ?? false),
                'pending_topup'   => $pendingTopup,
                'total_deposited' => (float) ($wallet?->total_earned    ?? 0),
                'total_spent'     => (float) ($wallet?->total_withdrawn  ?? 0),
            ],
        ]);
    }

    // GET /wallet/transactions
    public function transactions(Request $request)
    {
        $wallet = $request->user()->wallet;

        if (!$wallet) {
            return response()->json(['success' => true, 'data' => ['data' => [], 'total' => 0]]);
        }

        $txns = DB::table('transactions')
            ->where('wallet_id', $wallet->id)
            ->orderByDesc('created_at')
            ->paginate(30);

        // Enrich each transaction with a category label
        $items = collect($txns->items())->map(function ($tx) {
            $note = $tx->note ?? '';
            $type = $tx->type; // credit | debit
            $pm   = $tx->payment_method ?? '';

            // Determine category
            if (str_contains($note, 'top-up') || str_contains($note, 'topup') || $pm === 'waafi') {
                $category = 'topup';
                $icon     = 'account_balance_wallet';
            } elseif (str_contains($note, 'Transfer to') || str_contains($note, 'Sent to')) {
                $category = 'transfer_out';
                $icon     = 'send';
            } elseif (str_contains($note, 'Transfer from') || str_contains($note, 'Received from')) {
                $category = 'transfer_in';
                $icon     = 'move_to_inbox';
            } elseif (str_contains($note, 'Withdrawal') || str_contains($note, 'withdrawal') || str_contains($note, 'withdraw')) {
                $category = 'withdrawal';
                $icon     = 'arrow_upward';
            } elseif (str_contains($note, 'order') || str_contains($note, 'Order') || str_contains($note, 'purchase') || $pm === 'wallet') {
                $category = 'purchase';
                $icon     = 'shopping_bag';
            } elseif (str_contains($note, 'refund') || str_contains($note, 'Refund')) {
                $category = 'refund';
                $icon     = 'undo';
            } elseif (str_contains($note, 'Admin') || str_contains($note, 'admin')) {
                $category = 'admin_credit';
                $icon     = 'admin_panel_settings';
            } else {
                $category = $type === 'credit' ? 'credit' : 'debit';
                $icon     = $type === 'credit' ? 'arrow_downward' : 'arrow_upward';
            }

            return array_merge((array)$tx, [
                'category'   => $category,
                'icon_name'  => $icon,
                'amount'     => (float) $tx->amount,
                'balance_after' => (float) ($tx->balance_after ?? 0),
            ]);
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'data'          => $items,
                'total'         => $txns->total(),
                'current_page'  => $txns->currentPage(),
                'last_page'     => $txns->lastPage(),
            ],
        ]);
    }

    // POST /wallet/topup — initiate Waafi Pay top-up
    public function topup(Request $request)
    {
        $v = Validator::make($request->all(), [
            'amount'  => 'required|numeric|min:0.1|max:10000',
            'phone'   => 'required|string|min:9',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        if (!$this->waafi->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'Payment gateway not configured. Please contact admin.'], 503);
        }

        $user      = $request->user();
        $amount    = (float) $request->amount;
        $reference = 'WFI-' . strtoupper(Str::random(12));

        // Record pending
        $ptxId = DB::table('payment_transactions')->insertGetId([
            'reference'        => $reference,
            'user_id'          => $user->id,
            'gateway'          => 'waafi',
            'amount'           => $amount,
            'currency'         => 'USD',
            'transaction_type' => 'topup',
            'phone'            => $request->phone,
            'status'           => 'pending',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // Call Waafi Pay
        $result = $this->waafi->initiatePayment($request->phone, $amount, $reference, 'eSahlan Wallet Top-up');

        DB::table('payment_transactions')->where('id', $ptxId)->update([
            'gateway_reference' => $result['gateway_reference'],
            'gateway_response'  => json_encode($result['raw']),
            'status'            => $result['status'],
            'updated_at'        => now(),
        ]);

        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => $result['message'], 'code' => $result['response_code']], 422);
        }

        // If immediately approved (2001), credit wallet now
        if ($result['status'] === 'success') {
            $wallet = Wallet::getOrCreateFor('App\\Models\\User', $user->id);
            $wallet->credit($amount, 'Wallet top-up via Waafi Pay', 'payment_transactions', $ptxId, 'waafi');
            DB::table('payment_transactions')->where('id', $ptxId)->update(['transaction_type' => 'topup_done', 'updated_at' => now()]);

            return response()->json([
                'success'   => true,
                'status'    => 'success',
                'reference' => $reference,
                'message'   => 'Wallet credited successfully',
                'balance'   => (float) Wallet::getOrCreateFor('App\\Models\\User', $user->id)->balance,
            ]);
        }

        return response()->json([
            'success'   => true,
            'status'    => 'pending',
            'reference' => $reference,
            'message'   => 'Confirmation sent to your phone. Please approve and wait...',
        ]);
    }

    // GET /wallet/topup/status/{reference}
    public function topupStatus(Request $request, string $reference)
    {
        $ptx = DB::table('payment_transactions')
            ->where('reference', $reference)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$ptx) return response()->json(['success' => false, 'message' => 'Not found'], 404);

        if ($ptx->transaction_type === 'topup_done') {
            return response()->json(['success' => true, 'status' => 'success', 'message' => 'Wallet credited']);
        }
        if ($ptx->status === 'failed') {
            return response()->json(['success' => true, 'status' => 'failed', 'message' => 'Payment failed or rejected']);
        }

        // Poll gateway
        $result = $this->waafi->checkStatus($reference);

        if ($result['status'] === 'success' && $ptx->transaction_type === 'topup') {
            DB::transaction(function () use ($ptx, $reference, $result) {
                $wallet = Wallet::getOrCreateFor('App\\Models\\User', $ptx->user_id);
                $wallet->credit((float) $ptx->amount, 'Wallet top-up via Waafi Pay', 'payment_transactions', $ptx->id, 'waafi');
                DB::table('payment_transactions')->where('reference', $reference)->update([
                    'transaction_type' => 'topup_done',
                    'status'           => 'success',
                    'gateway_response' => json_encode($result['raw']),
                    'updated_at'       => now(),
                ]);
            });
            $wallet = Wallet::getOrCreateFor('App\\Models\\User', $ptx->user_id);
            return response()->json(['success' => true, 'status' => 'success', 'message' => 'Wallet credited', 'balance' => (float) $wallet->balance]);
        }

        if ($result['status'] === 'failed') {
            DB::table('payment_transactions')->where('reference', $reference)->update(['status' => 'failed', 'updated_at' => now()]);
        }

        return response()->json(['success' => true, 'status' => $result['status'], 'message' => $result['message']]);
    }

    // POST /wallet/topup/mobile-pay — submit Mobile Pay proof, PENDING admin approval
    // SECURITY: wallet is NOT credited here. Admin must approve via admin panel first.
    public function topupMobilePay(Request $request)
    {
        $v = Validator::make($request->all(), [
            'amount'       => 'required|numeric|min:1|max:10000',
            'proof_token'  => 'required|string',
            'sender_phone' => 'nullable|string|max:30',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user   = $request->user();
        $amount = (float) $request->amount;
        $token  = $request->proof_token;

        // Resolve screenshot URL from proof cache
        $proof = \Illuminate\Support\Facades\Cache::get("mobile_pay_proof:{$token}");
        if (!$proof) {
            return response()->json(['success' => false, 'message' => 'Proof not found or expired. Please upload proof again.'], 422);
        }

        // Prevent duplicate pending requests for same user
        $existing = DB::table('wallet_topup_requests')
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();
        if ($existing) {
            return response()->json(['success' => false, 'message' => 'You already have a pending top-up request. Please wait for admin approval.'], 422);
        }

        DB::table('wallet_topup_requests')->insert([
            'user_id'        => $user->id,
            'amount'         => $amount,
            'proof_token'    => $token,
            'screenshot_url' => $proof['image_url'] ?? null,
            'sender_phone'   => $request->sender_phone ?? $proof['phone'] ?? null,
            'account_id'     => $proof['account_id'] ?? null,
            'account_name'   => null,
            'status'         => 'pending',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // FCM: tell user request is pending review
        if ($user->fcm_token) {
            \App\Services\FcmService::sendToToken(
                $user->fcm_token,
                '⏳ Top-up Under Review',
                '$' . number_format($amount, 2) . ' top-up submitted. Admin will verify and credit your wallet within 30 minutes.',
                ['type' => 'topup_pending', 'amount' => (string)$amount, 'deep_link' => '/wallet']
            );
        }

        return response()->json([
            'success' => true,
            'status'  => 'pending',
            'message' => 'Top-up request submitted. Admin will verify your payment and credit your wallet within 30 minutes.',
        ]);
    }

    // POST /wallet/topup/mobile-pay/approve — admin approves and credits wallet
    // Called by AdminWalletController, not exposed to users
    public static function approveTopup(int $requestId, int $adminId, ?string $note = null): array
    {
        $req = DB::table('wallet_topup_requests')->where('id', $requestId)->where('status', 'pending')->first();
        if (!$req) return ['success' => false, 'message' => 'Request not found or already processed'];

        DB::table('wallet_topup_requests')->where('id', $requestId)->update([
            'status'      => 'approved',
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
            'admin_note'  => $note,
            'updated_at'  => now(),
        ]);

        $reference = 'MPY-' . strtoupper(Str::random(12));
        $ptxId = DB::table('payment_transactions')->insertGetId([
            'reference'        => $reference,
            'user_id'          => $req->user_id,
            'gateway'          => 'mobile_pay',
            'amount'           => $req->amount,
            'currency'         => 'USD',
            'transaction_type' => 'topup_done',
            'phone'            => $req->sender_phone,
            'status'           => 'success',
            'gateway_reference'=> $req->proof_token,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $wallet = Wallet::getOrCreateFor('App\\Models\\User', $req->user_id);
        $wallet->credit((float)$req->amount, 'Wallet top-up via Mobile Pay', 'payment_transactions', $ptxId, 'mobile_pay');

        return ['success' => true, 'balance' => (float)$wallet->balance];
    }

    // POST /wallet/send — transfer to another user
    public function send(Request $request)
    {
        $v = Validator::make($request->all(), [
            'phone'  => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'note'   => 'nullable|string|max:200',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $sender   = $request->user();
        $amount   = (float) $request->amount;
        $phone    = preg_replace('/\D/', '', $request->phone);

        // Find recipient
        $recipient = DB::table('users')->where('phone', $phone)->orWhere('phone', '+' . $phone)->first();
        if (!$recipient) return response()->json(['success' => false, 'message' => 'User not found with that phone number'], 404);
        if ($recipient->id === $sender->id) return response()->json(['success' => false, 'message' => 'Cannot send to yourself'], 422);

        $senderWallet = $sender->wallet;
        if (!$senderWallet || $senderWallet->balance < $amount) {
            return response()->json(['success' => false, 'message' => 'Insufficient wallet balance'], 422);
        }

        DB::transaction(function () use ($sender, $recipient, $amount, $request, $senderWallet) {
            $recipientWallet = Wallet::getOrCreateFor('App\\Models\\User', $recipient->id);
            $note            = $request->note ? ': ' . $request->note : '';

            // Debit sender
            $senderWallet->debit($amount, "Transfer to {$recipient->name}{$note}", null, null);

            // Credit recipient
            $recipientWallet->credit($amount, "Transfer from {$sender->name}{$note}", null, null, 'wallet_transfer');
        });

        return response()->json([
            'success'     => true,
            'message'     => "Successfully sent \${$amount} to {$recipient->name}",
            'recipient'   => $recipient->name,
            'new_balance' => (float) $sender->wallet->fresh()->balance,
        ]);
    }

    // POST /wallet/withdraw
    public function requestWithdrawal(Request $request)
    {
        $v = Validator::make($request->all(), [
            'amount'         => 'required|numeric|min:1',
            'payment_method' => 'required|in:waafi,evc,others',
            'account_number' => 'required|string',
            'account_name'   => 'required|string',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user   = $request->user();
        $wallet = $user->wallet;
        $amount = (float) $request->amount;

        if (!$wallet || $wallet->balance < $amount) {
            return response()->json(['success' => false, 'message' => 'Insufficient wallet balance'], 422);
        }

        DB::transaction(function () use ($wallet, $user, $amount, $request) {
            $wallet->debit($amount, 'Withdrawal request - pending admin approval');

            DB::table('withdrawal_requests')->insert([
                'wallet_id'      => $wallet->id,
                'owner_type'     => 'App\\Models\\User',
                'owner_id'       => $user->id,
                'amount'         => $amount,
                'method'         => $request->payment_method,
                'payment_method' => $request->payment_method,
                'account_number' => $request->account_number,
                'account_name'   => $request->account_name,
                'status'         => 'pending',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        });

        return response()->json(['success' => true, 'message' => 'Withdrawal request submitted. Admin will process within 24 hours.']);
    }

    // GET /wallet/loyalty-points
    public function loyaltyPoints(Request $request)
    {
        $user    = $request->user();
        $history = DB::table('loyalty_transactions')->where('user_id', $user->id)->latest()->limit(20)->get();

        return response()->json([
            'success' => true,
            'data'    => ['points' => (int) ($user->loyalty_points ?? 0), 'history' => $history],
        ]);
    }

    // GET /wallet/referral
    public function referral(Request $request)
    {
        $user  = $request->user();
        $stats = ReferralService::getReferralStats($user->id);

        return response()->json([
            'success' => true,
            'data'    => [
                'referral_code'   => $user->referral_code,
                'referral_count'  => $stats['total'],
                'rewarded_count'  => $stats['rewarded'],
                'pending_count'   => $stats['pending'],
                'total_earned_pts'=> $stats['earned'],
                'referrals'       => $stats['list'],
            ],
        ]);
    }

    // POST /wallet/verify-pin
    public function verifyPin(Request $request)
    {
        $v = Validator::make($request->all(), ['pin' => 'required|string|size:4']);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => 'PIN must be 4 digits'], 422);
        }

        $user = $request->user();
        $pinHash = $user->wallet_pin ?? $user->password;
        if (!Hash::check($request->pin, $pinHash)) {
            return response()->json(['success' => false, 'message' => 'Incorrect PIN. Please try again.'], 422);
        }

        return response()->json(['success' => true, 'message' => 'PIN verified']);
    }

    // POST /wallet/set-pin
    public function setPin(Request $request)
    {
        $v = Validator::make($request->all(), ['pin' => 'required|string|size:4']);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => 'PIN must be 4 digits'], 422);
        }

        $request->user()->update([
            'wallet_pin'     => Hash::make($request->pin),
            'has_wallet_pin' => true,
        ]);
        return response()->json(['success' => true, 'message' => 'Wallet PIN set successfully']);
    }
}
