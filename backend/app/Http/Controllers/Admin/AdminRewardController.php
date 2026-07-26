<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminRewardController extends Controller
{
    public function index()
    {
        $keys = [
            'reward_enabled', 'points_per_dollar', 'points_to_dollar',
            'points_expire_days', 'min_order_for_points', 'max_redeem_percent',
            'pts_mult_efood', 'pts_mult_egrocery', 'pts_mult_eshop',
            'pts_mult_eparcel', 'pts_mult_emoving', 'pts_mult_erent',
            'pts_mult_eticket', 'pts_mult_elearning', 'pts_mult_eexchange',
            'pts_mult_elaundry', 'pts_mult_edata', 'pts_mult_ehealth',
            'referral_enabled', 'referral_reward_pts', 'referral_commission_pct',
            'referral_l2_pct', 'referral_min_order',
        ];

        $settings = DB::table('settings')->whereIn('key', $keys)->pluck('value', 'key');

        // Stats
        $totalUsers    = DB::table('users')->where('points_balance', '>', 0)->count();
        $totalPts      = DB::table('users')->sum('points_balance');
        $totalEarned   = DB::table('loyalty_points')->where('type', 'earned')->sum('points');
        $totalRedeemed = DB::table('loyalty_points')->where('type', 'redeemed')->selectRaw('SUM(ABS(points))')->value('SUM(ABS(points))') ?? 0;

        // Referral stats
        $totalReferrals  = DB::table('referrals')->count();
        $rewardedReferrals = DB::table('referrals')->where('status', 'rewarded')->count();

        return view('admin.rewards.index', compact(
            'settings', 'totalUsers', 'totalPts', 'totalEarned', 'totalRedeemed',
            'totalReferrals', 'rewardedReferrals'
        ));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'reward_enabled'       => 'boolean',
            'points_per_dollar'    => 'integer|min:1|max:1000',
            'points_to_dollar'     => 'integer|min:1|max:10000',
            'points_expire_days'   => 'integer|min:30|max:3650',
            'min_order_for_points' => 'numeric|min:0',
            'max_redeem_percent'   => 'integer|min:1|max:100',
            'pts_mult_efood'       => 'integer|min:1|max:100',
            'pts_mult_egrocery'    => 'integer|min:1|max:100',
            'pts_mult_eshop'       => 'integer|min:1|max:100',
            'pts_mult_eparcel'     => 'integer|min:1|max:100',
            'pts_mult_emoving'     => 'integer|min:1|max:100',
            'pts_mult_erent'       => 'integer|min:1|max:100',
            'pts_mult_eticket'     => 'integer|min:1|max:100',
            'pts_mult_elearning'   => 'integer|min:1|max:100',
            'pts_mult_eexchange'   => 'integer|min:1|max:100',
            'pts_mult_elaundry'    => 'integer|min:1|max:100',
            'pts_mult_edata'       => 'integer|min:1|max:100',
            'pts_mult_ehealth'     => 'integer|min:1|max:100',
            'referral_enabled'     => 'boolean',
            'referral_reward_pts'  => 'integer|min:0|max:10000',
            'referral_commission_pct' => 'integer|min:0|max:50',
            'referral_l2_pct'      => 'integer|min:0|max:20',
            'referral_min_order'   => 'integer|min:0|max:1000',
        ]);

        foreach ($data as $key => $value) {
            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value, 'type' => is_bool($value) ? 'boolean' : 'integer']);
            Cache::forget("setting:{$key}");
        }

        // Boolean fields default to 0 if checkbox unchecked
        foreach (['reward_enabled', 'referral_enabled'] as $bool) {
            if (!$request->has($bool)) {
                DB::table('settings')->updateOrInsert(['key' => $bool], ['value' => '0']);
                Cache::forget("setting:{$bool}");
            }
        }

        return back()->with('success', 'Reward settings saved.');
    }

    public function ledger(Request $request)
    {
        $rows = DB::table('loyalty_points as lp')
            ->join('users', 'users.id', '=', 'lp.user_id')
            ->select('lp.*', 'users.name', 'users.phone')
            ->orderByDesc('lp.created_at')
            ->paginate(30);
        return view('admin.rewards.ledger', compact('rows'));
    }
}
