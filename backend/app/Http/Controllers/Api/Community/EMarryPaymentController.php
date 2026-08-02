<?php

namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Services\WaafiPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EMarryPaymentController extends Controller
{
    // ── Catalog constants ─────────────────────────────────────────────────────

    const FREE_DAILY_SWIPES = 10;

    const PLANS = [
        'premium' => [
            'key'           => 'premium',
            'name'          => 'Premium',
            'price'         => 4.99,
            'duration_days' => 30,
            'color'         => '#FF8A00',
            'features'      => [
                'Unlimited swipes',
                'Chat with all matches',
                'See who liked you',
                'Profile boost (1×/month)',
            ],
        ],
        'gold' => [
            'key'                => 'gold',
            'name'               => 'Gold',
            'price'              => 9.99,
            'duration_days'      => 30,
            'color'              => '#F59E0B',
            'daily_super_likes'  => 3,
            'features'           => [
                'Everything in Premium',
                '3 Super Likes / day',
                'Priority in Discover',
                'Read receipts',
                'Advanced filters',
            ],
        ],
    ];

    const CREDIT_PACKAGES = [
        'starter' => ['key' => 'starter', 'credits' => 5,  'price' => 2.49, 'label' => 'Starter'],
        'popular' => ['key' => 'popular', 'credits' => 15, 'price' => 5.99, 'label' => 'Popular', 'badge' => 'Best Value'],
        'bundle'  => ['key' => 'bundle',  'credits' => 35, 'price' => 11.99,'label' => 'Bundle'],
    ];

    const CREDIT_ACTIONS = [
        'super_like' => 1,
        'boost'      => 2,
        'undo'       => 1,
    ];

    // ── GET /emarry/payment/plans ─────────────────────────────────────────────
    public function plans(Request $request)
    {
        $user   = $request->user();
        $status = $this->_userStatus($user->id);

        return response()->json([
            'success'          => true,
            'data'             => [
                'plans'          => array_values(self::PLANS),
                'credit_packages'=> array_values(self::CREDIT_PACKAGES),
                'credit_actions' => self::CREDIT_ACTIONS,
                'free_daily_swipes' => self::FREE_DAILY_SWIPES,
                'my_status'      => $status,
            ],
        ]);
    }

    // ── GET /emarry/payment/status ────────────────────────────────────────────
    public function status(Request $request)
    {
        return response()->json([
            'success' => true,
            'data'    => $this->_userStatus($request->user()->id),
        ]);
    }

    // ── POST /emarry/payment/subscribe ───────────────────────────────────────
    public function subscribe(Request $request)
    {
        $v = Validator::make($request->all(), [
            'plan'           => 'required|in:premium,gold',
            'payment_method' => 'required|in:waafi_pay,epay,mobile_pay',
            'phone'          => 'required_if:payment_method,waafi_pay|nullable|string|min:9',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user   = $request->user();
        $plan   = self::PLANS[$request->plan];
        $amount = $plan['price'];
        $ref    = 'EM-SUB-' . strtoupper(Str::random(10));

        return match ($request->payment_method) {
            'waafi_pay'  => $this->_subscribeWaafi($user, $plan, $amount, $ref, $request->phone),
            'epay'       => $this->_subscribeEPay($user, $plan, $amount, $ref),
            'mobile_pay' => $this->_subscribeMobilePay($user, $plan, $amount, $ref, $request->sender_phone ?? null),
        };
    }

    // ── POST /emarry/payment/credits/buy ─────────────────────────────────────
    public function buyCredits(Request $request)
    {
        $v = Validator::make($request->all(), [
            'package'        => 'required|in:starter,popular,bundle',
            'payment_method' => 'required|in:waafi_pay,epay,mobile_pay',
            'phone'          => 'required_if:payment_method,waafi_pay|nullable|string|min:9',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user    = $request->user();
        $pkg     = self::CREDIT_PACKAGES[$request->package];
        $amount  = $pkg['price'];
        $ref     = 'EM-CR-' . strtoupper(Str::random(10));

        return match ($request->payment_method) {
            'waafi_pay'  => $this->_creditsWaafi($user, $pkg, $amount, $ref, $request->phone),
            'epay'       => $this->_creditsEPay($user, $pkg, $amount, $ref),
            'mobile_pay' => $this->_creditsMobilePay($user, $pkg, $amount, $ref, $request->sender_phone ?? null),
        };
    }

    // ── POST /emarry/payment/mobile-pay/verify ────────────────────────────────
    // User uploads screenshot after sending via USSD
    public function uploadMobilePayScreenshot(Request $request)
    {
        $v = Validator::make($request->all(), [
            'request_id' => 'required|integer|exists:emarry_mobile_pay_requests,id',
            'screenshot' => 'required|image|max:5120',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $req = DB::table('emarry_mobile_pay_requests')
            ->where('id', $request->request_id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->first();

        if (!$req) return response()->json(['success' => false, 'message' => 'Request not found'], 404);

        $path = $request->file('screenshot')->store('emarry/mobile_pay', 'public');
        DB::table('emarry_mobile_pay_requests')->where('id', $req->id)->update([
            'screenshot_url' => Storage::url($path),
            'updated_at'     => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Screenshot uploaded. Pending admin review.']);
    }

    // ── GET /emarry/payment/poll/{ref} ────────────────────────────────────────
    // Poll WaafiPay payment status and activate subscription/credits on success
    public function poll(Request $request, string $ref)
    {
        $ptx = DB::table('payment_transactions')
            ->where('reference', $ref)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$ptx) return response()->json(['success' => false, 'message' => 'Not found'], 404);

        if ($ptx->status === 'success') {
            return response()->json(['success' => true, 'status' => 'success', 'data' => $this->_userStatus($request->user()->id)]);
        }
        if ($ptx->status === 'failed') {
            return response()->json(['success' => true, 'status' => 'failed']);
        }

        // Poll WaafiPay
        $waafi  = app(WaafiPayService::class);
        $result = $waafi->checkStatus($ref);

        if ($result['status'] !== $ptx->status) {
            DB::table('payment_transactions')->where('reference', $ref)->update([
                'status'     => $result['status'],
                'updated_at' => now(),
            ]);
        }

        if ($result['status'] === 'success') {
            $meta = json_decode($ptx->metadata, true);
            if (($meta['item_type'] ?? '') === 'subscription') {
                $this->_activateSubscription($request->user()->id, $meta['plan'], $ptx->amount, 'waafi_pay', $ref);
            } elseif (($meta['item_type'] ?? '') === 'credits') {
                $this->_grantCredits($request->user()->id, (int)$meta['credits'], 'purchase', 'waafi_pay', $ref, $ptx->amount);
            }
            return response()->json(['success' => true, 'status' => 'success', 'data' => $this->_userStatus($request->user()->id)]);
        }

        return response()->json(['success' => true, 'status' => $result['status']]);
    }

    // ── POST /emarry/credits/use ──────────────────────────────────────────────
    // Deduct credits for an action (super_like, boost, undo)
    public function useCredit(Request $request)
    {
        $v = Validator::make($request->all(), ['action' => 'required|in:super_like,boost,undo']);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user   = $request->user();
        $cost   = self::CREDIT_ACTIONS[$request->action];
        $credit = DB::table('emarry_credits')->where('user_id', $user->id)->first();
        $balance = $credit->balance ?? 0;

        if ($balance < $cost) {
            return response()->json(['success' => false, 'message' => 'Not enough credits', 'balance' => $balance], 402);
        }

        DB::table('emarry_credits')->where('user_id', $user->id)->update([
            'balance'    => DB::raw("balance - $cost"),
            'updated_at' => now(),
        ]);
        DB::table('emarry_credit_transactions')->insert([
            'user_id'     => $user->id,
            'amount'      => -$cost,
            'type'        => $request->action,
            'description' => ucwords(str_replace('_', ' ', $request->action)),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return response()->json([
            'success' => true,
            'balance' => max(0, $balance - $cost),
        ]);
    }

    // ── Swipe limit check (called by EMarryController) ────────────────────────
    public static function checkSwipeLimitOrFail(int $userId): ?array
    {
        // Has active subscription → unlimited
        $sub = DB::table('emarry_subscriptions')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();
        if ($sub) return null;

        // Count today's swipes (likes + passes)
        $todayLikes = DB::table('emarry_interests')
            ->where('sender_id', $userId)
            ->whereDate('created_at', today())
            ->count();
        $todayPasses = DB::table('emarry_passes')
            ->where('user_id', $userId)
            ->whereDate('created_at', today())
            ->count();

        $used = $todayLikes + $todayPasses;
        if ($used >= self::FREE_DAILY_SWIPES) {
            return [
                'message'    => 'Daily swipe limit reached. Upgrade to Premium for unlimited swipes.',
                'limit'      => self::FREE_DAILY_SWIPES,
                'used'       => $used,
                'upgrade_required' => true,
            ];
        }
        return null;
    }

    // ── Private: WaafiPay flows ───────────────────────────────────────────────

    private function _subscribeWaafi($user, array $plan, float $amount, string $ref, ?string $phone)
    {
        $waafi = app(WaafiPayService::class);
        if (!$waafi->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'WaafiPay not configured'], 503);
        }

        DB::table('payment_transactions')->insert([
            'reference'        => $ref,
            'user_id'          => $user->id,
            'gateway'          => 'waafi',
            'amount'           => $amount,
            'currency'         => 'USD',
            'transaction_type' => 'emarry_subscription',
            'phone'            => $phone,
            'status'           => 'pending',
            'metadata'         => json_encode(['item_type' => 'subscription', 'plan' => $plan['key']]),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $result = $waafi->initiatePayment($phone, $amount, $ref, "eMarry {$plan['name']} Subscription");

        DB::table('payment_transactions')->where('reference', $ref)->update([
            'gateway_reference' => $result['gateway_reference'],
            'gateway_response'  => json_encode($result['raw']),
            'status'            => $result['status'],
            'updated_at'        => now(),
        ]);

        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => $result['message']]);
        }

        if ($result['status'] === 'success') {
            $this->_activateSubscription($user->id, $plan['key'], $amount, 'waafi_pay', $ref);
        }

        return response()->json([
            'success'   => true,
            'reference' => $ref,
            'status'    => $result['status'],
            'message'   => $result['message'],
            'poll_url'  => '/emarry/payment/poll/' . $ref,
        ]);
    }

    private function _subscribeEPay($user, array $plan, float $amount, string $ref)
    {
        $wallet = Wallet::getOrCreateFor('App\\Models\\User', $user->id);

        if ((float)$wallet->balance < $amount) {
            return response()->json([
                'success' => false,
                'message' => "Insufficient ePay balance. You have \${$wallet->balance}, need \${$amount}.",
                'balance' => (float)$wallet->balance,
            ], 402);
        }

        $wallet->debit($amount, "eMarry {$plan['name']} Subscription", null, null, 'epay');
        $this->_activateSubscription($user->id, $plan['key'], $amount, 'epay', $ref);

        return response()->json([
            'success' => true,
            'status'  => 'success',
            'message' => "eMarry {$plan['name']} activated!",
            'data'    => $this->_userStatus($user->id),
        ]);
    }

    private function _subscribeMobilePay($user, array $plan, float $amount, string $ref, ?string $senderPhone)
    {
        $mpAccount = DB::table('mobile_pay_accounts')->where('is_active', true)->orderBy('sort_order')->first();
        if (!$mpAccount) {
            return response()->json(['success' => false, 'message' => 'Mobile Pay not available'], 503);
        }

        $requestId = DB::table('emarry_mobile_pay_requests')->insertGetId([
            'user_id'      => $user->id,
            'item_type'    => 'subscription',
            'item_key'     => $plan['key'],
            'amount'       => $amount,
            'sender_phone' => $senderPhone ?? '',
            'status'       => 'pending',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return response()->json([
            'success'    => true,
            'status'     => 'pending_manual',
            'request_id' => $requestId,
            'message'    => 'Send payment via Mobile Pay then upload screenshot.',
            'mobile_pay' => [
                'account_name'   => $mpAccount->name,
                'account_number' => $mpAccount->account_number,
                'amount'         => $amount,
                'ussd'           => (new \App\Models\MobilePayAccount)->fill((array)$mpAccount)->buildUssd($amount),
                'instructions'   => $mpAccount->instructions,
            ],
        ]);
    }

    private function _creditsWaafi($user, array $pkg, float $amount, string $ref, ?string $phone)
    {
        $waafi = app(WaafiPayService::class);
        if (!$waafi->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'WaafiPay not configured'], 503);
        }

        DB::table('payment_transactions')->insert([
            'reference'        => $ref,
            'user_id'          => $user->id,
            'gateway'          => 'waafi',
            'amount'           => $amount,
            'currency'         => 'USD',
            'transaction_type' => 'emarry_credits',
            'phone'            => $phone,
            'status'           => 'pending',
            'metadata'         => json_encode(['item_type' => 'credits', 'credits' => $pkg['credits'], 'package' => $pkg['key']]),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $result = $waafi->initiatePayment($phone, $amount, $ref, "eMarry {$pkg['credits']} Credits");

        DB::table('payment_transactions')->where('reference', $ref)->update([
            'gateway_reference' => $result['gateway_reference'],
            'gateway_response'  => json_encode($result['raw']),
            'status'            => $result['status'],
            'updated_at'        => now(),
        ]);

        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => $result['message']]);
        }

        if ($result['status'] === 'success') {
            $this->_grantCredits($user->id, $pkg['credits'], 'purchase', 'waafi_pay', $ref, $amount);
        }

        return response()->json([
            'success'   => true,
            'reference' => $ref,
            'status'    => $result['status'],
            'message'   => $result['message'],
            'poll_url'  => '/emarry/payment/poll/' . $ref,
        ]);
    }

    private function _creditsEPay($user, array $pkg, float $amount, string $ref)
    {
        $wallet = Wallet::getOrCreateFor('App\\Models\\User', $user->id);

        if ((float)$wallet->balance < $amount) {
            return response()->json([
                'success' => false,
                'message' => "Insufficient ePay balance. You have \${$wallet->balance}, need \${$amount}.",
                'balance' => (float)$wallet->balance,
            ], 402);
        }

        $wallet->debit($amount, "eMarry {$pkg['credits']} Credits", null, null, 'epay');
        $this->_grantCredits($user->id, $pkg['credits'], 'purchase', 'epay', $ref, $amount);

        return response()->json([
            'success' => true,
            'status'  => 'success',
            'message' => "{$pkg['credits']} credits added!",
            'data'    => $this->_userStatus($user->id),
        ]);
    }

    private function _creditsMobilePay($user, array $pkg, float $amount, string $ref, ?string $senderPhone)
    {
        $mpAccount = DB::table('mobile_pay_accounts')->where('is_active', true)->orderBy('sort_order')->first();
        if (!$mpAccount) {
            return response()->json(['success' => false, 'message' => 'Mobile Pay not available'], 503);
        }

        $requestId = DB::table('emarry_mobile_pay_requests')->insertGetId([
            'user_id'      => $user->id,
            'item_type'    => 'credits',
            'item_key'     => $pkg['key'],
            'amount'       => $amount,
            'sender_phone' => $senderPhone ?? '',
            'status'       => 'pending',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return response()->json([
            'success'    => true,
            'status'     => 'pending_manual',
            'request_id' => $requestId,
            'message'    => 'Send payment then upload screenshot for confirmation.',
            'mobile_pay' => [
                'account_name'   => $mpAccount->name,
                'account_number' => $mpAccount->account_number,
                'amount'         => $amount,
                'ussd'           => (new \App\Models\MobilePayAccount)->fill((array)$mpAccount)->buildUssd($amount),
                'instructions'   => $mpAccount->instructions,
            ],
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function _activateSubscription(int $userId, string $planKey, float $amount, string $method, string $ref): void
    {
        // Expire any existing active sub
        DB::table('emarry_subscriptions')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->update(['status' => 'expired', 'updated_at' => now()]);

        $days = self::PLANS[$planKey]['duration_days'];
        DB::table('emarry_subscriptions')->insert([
            'user_id'          => $userId,
            'plan'             => $planKey,
            'status'           => 'active',
            'payment_method'   => $method,
            'payment_reference'=> $ref,
            'amount'           => $amount,
            'starts_at'        => now(),
            'expires_at'       => now()->addDays($days),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    private function _grantCredits(int $userId, int $credits, string $type, string $method, string $ref, float $paid): void
    {
        DB::table('emarry_credits')->upsert(
            ['user_id' => $userId, 'balance' => $credits, 'created_at' => now(), 'updated_at' => now()],
            ['user_id'],
            ['balance' => DB::raw("balance + $credits"), 'updated_at' => now()]
        );
        DB::table('emarry_credit_transactions')->insert([
            'user_id'           => $userId,
            'amount'            => $credits,
            'type'              => $type,
            'payment_method'    => $method,
            'payment_reference' => $ref,
            'paid_amount'       => $paid,
            'description'       => "$credits credits purchased",
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    private function _userStatus(int $userId): array
    {
        $sub = DB::table('emarry_subscriptions')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->orderByDesc('expires_at')
            ->first();

        $credit  = DB::table('emarry_credits')->where('user_id', $userId)->first();
        $balance = $credit->balance ?? 0;

        $todayLikes = DB::table('emarry_interests')
            ->where('sender_id', $userId)->whereDate('created_at', today())->count();
        $todayPasses = DB::table('emarry_passes')
            ->where('user_id', $userId)->whereDate('created_at', today())->count();
        $todaySwipes = $todayLikes + $todayPasses;

        $epayBalance = 0.0;
        try {
            $wallet = Wallet::getOrCreateFor('App\\Models\\User', $userId);
            $epayBalance = (float)$wallet->balance;
        } catch (\Throwable) {}

        return [
            'is_premium'        => (bool) $sub,
            'plan'              => $sub ? $sub->plan : null,
            'plan_expires_at'   => $sub ? $sub->expires_at : null,
            'credits'           => (int)$balance,
            'today_swipes'      => $todaySwipes,
            'free_daily_swipes' => self::FREE_DAILY_SWIPES,
            'swipes_remaining'  => $sub ? null : max(0, self::FREE_DAILY_SWIPES - $todaySwipes),
            'epay_balance'      => $epayBalance,
        ];
    }
}
