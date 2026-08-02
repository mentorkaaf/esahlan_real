<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminEMarryController extends Controller
{
    // ── GET /admin/emarry ─────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $status = $request->get('status', 'pending');

        $profiles = DB::table('emarry_profiles as p')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->when($status !== 'all', fn($q) => $q->where('p.status', $status))
            ->select(
                'p.id', 'p.user_id', 'p.gender', 'p.looking_for', 'p.age',
                'p.city', 'p.education', 'p.occupation', 'p.marital_status',
                'p.has_children', 'p.bio', 'p.photos', 'p.status',
                'p.fee_paid', 'p.is_visible', 'p.created_at', 'p.approved_at',
                'u.name', 'u.email', 'u.phone', 'u.avatar'
            )
            ->orderByDesc('p.created_at')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'pending'  => DB::table('emarry_profiles')->where('status', 'pending')->count(),
            'approved' => DB::table('emarry_profiles')->where('status', 'approved')->count(),
            'rejected' => DB::table('emarry_profiles')->where('status', 'rejected')->count(),
            'interests'=> DB::table('emarry_interests')->count(),
            'matched'  => DB::table('emarry_interests')->where('status', 'accepted')->count(),
        ];

        return view('admin.emarry.index', compact('profiles', 'stats', 'status'));
    }

    // ── POST /admin/emarry/{id}/approve ──────────────────────────────────────
    public function approve(Request $request, int $id)
    {
        $profile = DB::table('emarry_profiles')->where('id', $id)->first();
        if (!$profile) return back()->with('error', 'Profile not found');

        DB::table('emarry_profiles')->where('id', $id)->update([
            'status'      => 'approved',
            'is_visible'  => true,
            'approved_at' => now(),
            'updated_at'  => now(),
        ]);

        // Notify user
        try {
            $user = DB::table('users')->where('id', $profile->user_id)->first();
            if ($user?->fcm_token) {
                FcmService::sendToToken(
                    $user->fcm_token,
                    '💍 eMarry Profile Approved!',
                    'Your profile is now live. Start browsing potential matches!',
                    ['type' => 'emarry_approved', 'deep_link' => '/community?tab=emarry']
                );
            }
        } catch (\Throwable) {}

        return back()->with('success', 'Profile approved and is now visible.');
    }

    // ── POST /admin/emarry/{id}/reject ────────────────────────────────────────
    public function reject(Request $request, int $id)
    {
        $request->validate(['reason' => 'nullable|string|max:500']);
        $profile = DB::table('emarry_profiles')->where('id', $id)->first();
        if (!$profile) return back()->with('error', 'Profile not found');

        DB::table('emarry_profiles')->where('id', $id)->update([
            'status'     => 'rejected',
            'is_visible' => false,
            'updated_at' => now(),
        ]);

        try {
            $user = DB::table('users')->where('id', $profile->user_id)->first();
            if ($user?->fcm_token) {
                $reason = $request->reason
                    ? "Reason: {$request->reason}"
                    : 'Please review our community guidelines and resubmit.';
                FcmService::sendToToken(
                    $user->fcm_token,
                    'eMarry Profile Review',
                    "Your profile needs changes. $reason",
                    ['type' => 'emarry_rejected', 'deep_link' => '/community?tab=emarry']
                );
            }
        } catch (\Throwable) {}

        return back()->with('success', 'Profile rejected.');
    }

    // ── POST /admin/emarry/{id}/delete ────────────────────────────────────────
    public function delete(int $id)
    {
        DB::table('emarry_profiles')->where('id', $id)->delete();
        return back()->with('success', 'Profile deleted.');
    }

    // ── GET /admin/emarry/{id}/detail ─────────────────────────────────────────
    public function detail(int $id)
    {
        $profile = DB::table('emarry_profiles as p')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->where('p.id', $id)
            ->select('p.*', 'u.name', 'u.email', 'u.phone', 'u.avatar', 'u.created_at as joined_at')
            ->first();

        if (!$profile) abort(404);

        $interests_sent = DB::table('emarry_interests as i')
            ->join('users as u', 'u.id', '=', 'i.receiver_id')
            ->where('i.sender_id', $profile->user_id)
            ->select('i.*', 'u.name as receiver_name')
            ->orderByDesc('i.created_at')
            ->limit(20)
            ->get();

        $interests_received = DB::table('emarry_interests as i')
            ->join('users as u', 'u.id', '=', 'i.sender_id')
            ->where('i.receiver_id', $profile->user_id)
            ->select('i.*', 'u.name as sender_name')
            ->orderByDesc('i.created_at')
            ->limit(20)
            ->get();

        return view('admin.emarry.detail', compact('profile', 'interests_sent', 'interests_received'));
    }

    // ── GET /admin/emarry/interests ───────────────────────────────────────────
    public function interests(Request $request)
    {
        $interests = DB::table('emarry_interests as i')
            ->join('users as s', 's.id', '=', 'i.sender_id')
            ->join('users as r', 'r.id', '=', 'i.receiver_id')
            ->select(
                'i.id', 'i.status', 'i.message', 'i.created_at',
                's.name as sender_name', 's.id as sender_id',
                'r.name as receiver_name', 'r.id as receiver_id'
            )
            ->when($request->status, fn($q) => $q->where('i.status', $request->status))
            ->orderByDesc('i.created_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.emarry.interests', compact('interests'));
    }

    // ── GET /admin/emarry/monetization ────────────────────────────────────────
    public function monetization(Request $request)
    {
        $tab = $request->get('tab', 'overview');

        // ── Overview stats ────────────────────────────────────────────────────
        $stats = [
            'active_subs'     => DB::table('emarry_subscriptions')->where('status','active')->where('expires_at','>',now())->count(),
            'total_subs'      => DB::table('emarry_subscriptions')->count(),
            'revenue_total'   => (float) DB::table('emarry_subscriptions')->where('status','active')->sum('amount')
                               + (float) DB::table('emarry_credit_transactions')->where('amount','>',0)->sum('paid_amount'),
            'revenue_month'   => (float) DB::table('emarry_subscriptions')->where('created_at','>=',now()->startOfMonth())->sum('amount')
                               + (float) DB::table('emarry_credit_transactions')->where('amount','>',0)->where('created_at','>=',now()->startOfMonth())->sum('paid_amount'),
            'credits_sold'    => (int) DB::table('emarry_credit_transactions')->where('amount','>',0)->sum('amount'),
            'credits_used'    => (int) abs(DB::table('emarry_credit_transactions')->where('amount','<',0)->sum('amount')),
            'pending_mp'      => DB::table('emarry_mobile_pay_requests')->where('status','pending')->count(),
            'premium_users'   => DB::table('emarry_subscriptions')->where('status','active')->where('expires_at','>',now())->where('plan','premium')->count(),
            'gold_users'      => DB::table('emarry_subscriptions')->where('status','active')->where('expires_at','>',now())->where('plan','gold')->count(),
        ];

        // ── Subscriptions ─────────────────────────────────────────────────────
        $subscriptions = DB::table('emarry_subscriptions as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->select('s.*', 'u.name', 'u.email', 'u.phone', 'u.avatar')
            ->when($request->plan,   fn($q) => $q->where('s.plan', $request->plan))
            ->when($request->status, fn($q) => $q->where('s.status', $request->status))
            ->orderByDesc('s.created_at')
            ->paginate(20)->withQueryString();

        // ── Mobile Pay requests ───────────────────────────────────────────────
        $mobilePayRequests = DB::table('emarry_mobile_pay_requests as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->leftJoin('users as admin', 'admin.id', '=', 'r.reviewed_by')
            ->select('r.*', 'u.name', 'u.email', 'u.phone', 'admin.name as admin_name')
            ->when($request->mp_status ?? 'pending', fn($q) => $q->where('r.status', $request->mp_status ?? 'pending'))
            ->orderByDesc('r.created_at')
            ->paginate(20)->withQueryString();

        // ── Credit ledger ─────────────────────────────────────────────────────
        $creditTxns = DB::table('emarry_credit_transactions as t')
            ->join('users as u', 'u.id', '=', 't.user_id')
            ->select('t.*', 'u.name', 'u.email')
            ->orderByDesc('t.created_at')
            ->paginate(25)->withQueryString();

        // ── Revenue chart (last 30 days) ──────────────────────────────────────
        $revenueChart = DB::table('emarry_subscriptions')
            ->where('created_at', '>=', now()->subDays(29))
            ->selectRaw("DATE(created_at) as date, SUM(amount) as rev, COUNT(*) as subs")
            ->groupBy('date')->orderBy('date')->get();

        return view('admin.emarry.monetization', compact(
            'stats', 'subscriptions', 'mobilePayRequests', 'creditTxns', 'revenueChart', 'tab'
        ));
    }

    // ── POST /admin/emarry/monetization/mobile-pay/{id}/approve ──────────────
    public function approveMobilePay(Request $request, int $id)
    {
        $req = DB::table('emarry_mobile_pay_requests')->where('id', $id)->where('status', 'pending')->first();
        if (!$req) return back()->with('error', 'Request not found or already processed.');

        DB::table('emarry_mobile_pay_requests')->where('id', $id)->update([
            'status'      => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'admin_note'  => $request->admin_note,
            'updated_at'  => now(),
        ]);

        // Grant subscription or credits
        if ($req->item_type === 'subscription') {
            $plans = ['premium' => ['duration_days' => 30], 'gold' => ['duration_days' => 30]];
            $days  = $plans[$req->item_key]['duration_days'] ?? 30;
            DB::table('emarry_subscriptions')->where('user_id', $req->user_id)->where('status','active')
                ->update(['status' => 'expired', 'updated_at' => now()]);
            DB::table('emarry_subscriptions')->insert([
                'user_id'          => $req->user_id,
                'plan'             => $req->item_key,
                'status'           => 'active',
                'payment_method'   => 'mobile_pay',
                'payment_reference'=> 'MP-' . $id,
                'amount'           => $req->amount,
                'starts_at'        => now(),
                'expires_at'       => now()->addDays($days),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        } elseif ($req->item_type === 'credits') {
            $creditMap = ['starter' => 5, 'popular' => 15, 'bundle' => 35];
            $credits   = $creditMap[$req->item_key] ?? 5;
            DB::table('emarry_credits')->upsert(
                ['user_id' => $req->user_id, 'balance' => $credits, 'created_at' => now(), 'updated_at' => now()],
                ['user_id'],
                ['balance' => DB::raw("balance + $credits"), 'updated_at' => now()]
            );
            DB::table('emarry_credit_transactions')->insert([
                'user_id'           => $req->user_id,
                'amount'            => $credits,
                'type'              => 'purchase',
                'payment_method'    => 'mobile_pay',
                'payment_reference' => 'MP-' . $id,
                'paid_amount'       => $req->amount,
                'description'       => "$credits credits via Mobile Pay",
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }

        // FCM notify user
        try {
            $user = DB::table('users')->where('id', $req->user_id)->first();
            if ($user?->fcm_token) {
                $label = $req->item_type === 'subscription' ? ucfirst($req->item_key) . ' Plan' : ucfirst($req->item_key) . ' Credits';
                FcmService::sendToToken($user->fcm_token, '💍 eMarry Payment Approved',
                    "Your $label has been activated!", ['type' => 'emarry_payment', 'deep_link' => '/community?tab=emarry']);
            }
        } catch (\Throwable) {}

        return back()->with('success', 'Payment approved and access granted.');
    }

    // ── POST /admin/emarry/monetization/mobile-pay/{id}/reject ───────────────
    public function rejectMobilePay(Request $request, int $id)
    {
        DB::table('emarry_mobile_pay_requests')->where('id', $id)->where('status', 'pending')->update([
            'status'      => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'admin_note'  => $request->admin_note,
            'updated_at'  => now(),
        ]);

        try {
            $req  = DB::table('emarry_mobile_pay_requests')->where('id', $id)->first();
            $user = DB::table('users')->where('id', $req->user_id)->first();
            if ($user?->fcm_token) {
                FcmService::sendToToken($user->fcm_token, '❌ eMarry Payment Rejected',
                    'Your payment could not be verified. Please try again or contact support.',
                    ['type' => 'emarry_payment_rejected']);
            }
        } catch (\Throwable) {}

        return back()->with('success', 'Payment request rejected.');
    }

    // ── POST /admin/emarry/monetization/subscription/{id}/cancel ─────────────
    public function cancelSubscription(int $id)
    {
        DB::table('emarry_subscriptions')->where('id', $id)
            ->update(['status' => 'cancelled', 'updated_at' => now()]);
        return back()->with('success', 'Subscription cancelled.');
    }

    // ── POST /admin/emarry/monetization/credits/grant ─────────────────────────
    public function grantCredits(Request $request)
    {
        $request->validate(['user_id' => 'required|integer|exists:users,id', 'credits' => 'required|integer|min:1|max:1000']);
        $credits = (int)$request->credits;
        DB::table('emarry_credits')->upsert(
            ['user_id' => $request->user_id, 'balance' => $credits, 'created_at' => now(), 'updated_at' => now()],
            ['user_id'],
            ['balance' => DB::raw("balance + $credits"), 'updated_at' => now()]
        );
        DB::table('emarry_credit_transactions')->insert([
            'user_id'     => $request->user_id,
            'amount'      => $credits,
            'type'        => 'admin_grant',
            'description' => "Admin granted $credits credits",
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        return back()->with('success', "$credits credits granted.");
    }
}
