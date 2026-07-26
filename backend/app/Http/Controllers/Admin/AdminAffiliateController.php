<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminAffiliateController extends Controller
{
    public function index()
    {
        $affiliates = DB::table('affiliates as a')
            ->join('users', 'users.id', '=', 'a.user_id')
            ->select('a.*', 'users.name', 'users.phone')
            ->orderByDesc('a.created_at')
            ->paginate(20);

        $stats = [
            'total'    => DB::table('affiliates')->count(),
            'active'   => DB::table('affiliates')->where('status', 'active')->count(),
            'pending'  => DB::table('affiliates')->where('status', 'pending')->count(),
            'total_pts'=> DB::table('affiliates')->sum('total_earned_pts'),
        ];

        return view('admin.affiliates.index', compact('affiliates', 'stats'));
    }

    public function show(int $id)
    {
        $affiliate = DB::table('affiliates as a')
            ->join('users', 'users.id', '=', 'a.user_id')
            ->select('a.*', 'users.name', 'users.phone', 'users.points_balance')
            ->where('a.id', $id)
            ->first();

        abort_if(!$affiliate, 404);

        $conversions = DB::table('affiliate_conversions as ac')
            ->join('orders', 'orders.id', '=', 'ac.order_id')
            ->join('users', 'users.id', '=', 'ac.user_id')
            ->select('ac.*', 'users.name as buyer_name', 'orders.order_number')
            ->where('ac.affiliate_id', $id)
            ->orderByDesc('ac.created_at')
            ->paginate(15);

        $payouts = DB::table('affiliate_payouts')
            ->where('affiliate_id', $id)
            ->orderByDesc('created_at')
            ->get();

        return view('admin.affiliates.show', compact('affiliate', 'conversions', 'payouts'));
    }

    public function updateStatus(Request $request, int $id)
    {
        $request->validate(['status' => 'required|in:active,suspended,pending']);
        DB::table('affiliates')->where('id', $id)->update([
            'status'     => $request->status,
            'updated_at' => now(),
        ]);
        return back()->with('success', 'Status updated.');
    }

    public function payouts()
    {
        $payouts = DB::table('affiliate_payouts as ap')
            ->join('affiliates', 'affiliates.id', '=', 'ap.affiliate_id')
            ->join('users', 'users.id', '=', 'affiliates.user_id')
            ->select('ap.*', 'users.name', 'users.phone', 'affiliates.code')
            ->orderByDesc('ap.created_at')
            ->paginate(25);

        return view('admin.affiliates.payouts', compact('payouts'));
    }

    public function processPayout(Request $request, int $payoutId)
    {
        $request->validate([
            'action'     => 'required|in:approved,rejected',
            'admin_note' => 'nullable|string|max:500',
        ]);

        $payout = DB::table('affiliate_payouts')->where('id', $payoutId)->first();
        abort_if(!$payout || $payout->status !== 'pending', 404);

        if ($request->action === 'approved') {
            $affiliate = DB::table('affiliates')->where('id', $payout->affiliate_id)->first();

            // Credit wallet with dollar_value
            $wallet = Wallet::getOrCreateFor('App\\Models\\User', $affiliate->user_id);
            $wallet->credit($payout->dollar_value, 'affiliate_payout', "Affiliate payout #{$payoutId}");

            // Deduct points used for payout
            LoyaltyService::redeem(
                $affiliate->user_id,
                $payout->points_requested,
                $payout->dollar_value,
                "Affiliate payout #{$payoutId}",
                'affiliate_payout',
                $payoutId
            );

            DB::table('affiliates')->where('id', $affiliate->id)->update([
                'pending_payout_pts' => DB::raw("GREATEST(0, pending_payout_pts - {$payout->points_requested})"),
            ]);

            DB::table('affiliate_payouts')->where('id', $payoutId)->update([
                'status'       => 'paid',
                'admin_note'   => $request->admin_note,
                'processed_at' => now(),
                'updated_at'   => now(),
            ]);

            return back()->with('success', 'Payout approved and wallet credited.');
        }

        DB::table('affiliate_payouts')->where('id', $payoutId)->update([
            'status'       => 'rejected',
            'admin_note'   => $request->admin_note,
            'processed_at' => now(),
            'updated_at'   => now(),
        ]);

        return back()->with('success', 'Payout rejected.');
    }

    public function settings()
    {
        $keys = ['affiliate_enabled', 'affiliate_commission_pct', 'affiliate_payout_min_pts', 'affiliate_auto_approve', 'affiliate_cookie_days'];
        $settings = DB::table('settings')->whereIn('key', $keys)->pluck('value', 'key');
        return view('admin.affiliates.settings', compact('settings'));
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'affiliate_enabled'       => 'boolean',
            'affiliate_commission_pct'=> 'integer|min:1|max:50',
            'affiliate_payout_min_pts'=> 'integer|min:100',
            'affiliate_auto_approve'  => 'boolean',
            'affiliate_cookie_days'   => 'integer|min:1|max:365',
        ]);

        foreach ($data as $key => $value) {
            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value, 'type' => 'integer']);
            Cache::forget("setting:{$key}");
        }

        foreach (['affiliate_enabled', 'affiliate_auto_approve'] as $bool) {
            if (!$request->has($bool)) {
                DB::table('settings')->updateOrInsert(['key' => $bool], ['value' => '0']);
                Cache::forget("setting:{$bool}");
            }
        }

        return back()->with('success', 'Affiliate settings saved.');
    }
}
