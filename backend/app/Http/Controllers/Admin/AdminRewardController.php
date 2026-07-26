<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LoyaltyService;
use App\Services\TierService;
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
            'tier_silver_pts', 'tier_gold_pts', 'tier_platinum_pts',
            'tier_bronze_bonus', 'tier_silver_bonus', 'tier_gold_bonus', 'tier_platinum_bonus',
            'tier_bronze_redeem_extra', 'tier_silver_redeem_extra', 'tier_gold_redeem_extra', 'tier_platinum_redeem_extra',
        ];

        $settings = DB::table('settings')->whereIn('key', $keys)->pluck('value', 'key');

        // Stats
        $totalUsers    = DB::table('users')->where('points_balance', '>', 0)->count();
        $totalPts      = DB::table('users')->sum('points_balance');
        $totalEarned   = DB::table('loyalty_points')->where('type', 'earned')->sum('points');
        $totalRedeemed = DB::table('loyalty_points')->where('type', 'redeemed')->selectRaw('SUM(ABS(points))')->value('SUM(ABS(points))') ?? 0;

        // Referral stats
        $totalReferrals    = DB::table('referrals')->count();
        $rewardedReferrals = DB::table('referrals')->where('status', 'rewarded')->count();

        // Tier stats
        $tierStats = DB::table('users')
            ->selectRaw("tier, COUNT(*) as cnt")
            ->whereIn('tier', TierService::TIERS)
            ->groupBy('tier')
            ->pluck('cnt', 'tier')
            ->toArray();

        // Points earned last 30 days (daily chart)
        $chartData = DB::table('loyalty_points')
            ->where('type', 'earned')
            ->where('created_at', '>=', now()->subDays(29))
            ->selectRaw('DATE(created_at) as day, SUM(points) as pts, COUNT(DISTINCT user_id) as users')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $chartLabels = [];
        $chartPts    = [];
        $chartUsers  = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $chartLabels[] = now()->subDays($i)->format('M d');
            $chartPts[]    = (int) ($chartData[$day]->pts   ?? 0);
            $chartUsers[]  = (int) ($chartData[$day]->users ?? 0);
        }

        // Top earners
        $topEarners = DB::table('users')
            ->select('id', 'name', 'phone', 'points_balance', 'total_points_earned', 'tier')
            ->where('points_balance', '>', 0)
            ->orderByDesc('total_points_earned')
            ->limit(10)
            ->get();

        // Recent transactions
        $recentTxns = DB::table('loyalty_points as lp')
            ->join('users', 'users.id', '=', 'lp.user_id')
            ->select('lp.id', 'lp.points', 'lp.type', 'lp.note', 'lp.reference_type', 'lp.created_at', 'users.name', 'users.phone')
            ->orderByDesc('lp.created_at')
            ->limit(12)
            ->get();

        // Module breakdown (earned pts per module slug last 30 days)
        $moduleBreakdown = DB::table('loyalty_points')
            ->where('type', 'earned')
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('reference_type')
            ->where('reference_type', '!=', 'streak')
            ->where('reference_type', '!=', 'badge')
            ->selectRaw('reference_type as module, SUM(points) as pts, COUNT(*) as txns')
            ->groupBy('reference_type')
            ->orderByDesc('pts')
            ->get();

        // Pts earned this month vs last month
        $earnedThisMonth = DB::table('loyalty_points')->where('type','earned')->whereMonth('created_at', now()->month)->sum('points');
        $earnedLastMonth = DB::table('loyalty_points')->where('type','earned')->whereMonth('created_at', now()->subMonth()->month)->sum('points');
        $earnedGrowth    = $earnedLastMonth > 0 ? round((($earnedThisMonth - $earnedLastMonth) / $earnedLastMonth) * 100, 1) : 0;

        // Streak stats
        $activeStreaks  = DB::table('user_streaks')->where('current_streak', '>', 0)->count();
        $maxStreak      = DB::table('user_streaks')->max('longest_streak') ?? 0;
        $avgStreak      = round(DB::table('user_streaks')->avg('current_streak') ?? 0, 1);

        // Badge stats
        $totalBadgesAwarded = DB::table('user_badges')->count();
        $badgeBreakdown = DB::table('user_badges as ub')
            ->join('badges', 'badges.id', '=', 'ub.badge_id')
            ->selectRaw('badges.name, badges.icon, COUNT(*) as cnt')
            ->groupBy('badges.id', 'badges.name', 'badges.icon')
            ->orderByDesc('cnt')
            ->limit(8)
            ->get();

        return view('admin.rewards.index', compact(
            'settings', 'totalUsers', 'totalPts', 'totalEarned', 'totalRedeemed',
            'totalReferrals', 'rewardedReferrals', 'tierStats',
            'chartLabels', 'chartPts', 'chartUsers',
            'topEarners', 'recentTxns', 'moduleBreakdown',
            'earnedThisMonth', 'earnedLastMonth', 'earnedGrowth',
            'activeStreaks', 'maxStreak', 'avgStreak',
            'totalBadgesAwarded', 'badgeBreakdown'
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
            'tier_silver_pts'      => 'integer|min:1',
            'tier_gold_pts'        => 'integer|min:1',
            'tier_platinum_pts'    => 'integer|min:1',
            'tier_bronze_bonus'    => 'integer|min:0|max:200',
            'tier_silver_bonus'    => 'integer|min:0|max:200',
            'tier_gold_bonus'      => 'integer|min:0|max:200',
            'tier_platinum_bonus'  => 'integer|min:0|max:200',
            'tier_bronze_redeem_extra'   => 'integer|min:0|max:50',
            'tier_silver_redeem_extra'   => 'integer|min:0|max:50',
            'tier_gold_redeem_extra'     => 'integer|min:0|max:50',
            'tier_platinum_redeem_extra' => 'integer|min:0|max:50',
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
