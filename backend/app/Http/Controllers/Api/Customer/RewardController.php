<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Services\LoyaltyService;
use App\Services\RewardNotificationService;
use App\Services\TierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RewardController extends Controller
{
    // GET /rewards — balance + summary
    public function index(Request $request)
    {
        $user    = $request->user();
        $balance = LoyaltyService::balance($user->id);
        $rate    = LoyaltyService::pointsToDollar();
        $enabled = LoyaltyService::isEnabled();

        // Total earned / redeemed all-time
        $stats = DB::table('loyalty_points')
            ->where('user_id', $user->id)
            ->selectRaw('
                SUM(CASE WHEN type IN (\'earned\',\'bonus\') AND points > 0 THEN points ELSE 0 END) as total_earned,
                SUM(CASE WHEN type = \'redeemed\' THEN ABS(points) ELSE 0 END) as total_redeemed,
                COUNT(*) as transactions
            ')
            ->first();

        // Tier info
        $totalEarned = (int) DB::table('users')->where('id', $user->id)->value('total_points_earned');
        $tier        = $user->tier ?? 'bronze';
        $nextInfo    = TierService::nextTierInfo($totalEarned);
        $earnBonus   = TierService::earnMultiplier($user->id);
        $redeemExtra = TierService::redeemExtra($user->id);
        $allTiers    = TierService::allTiersInfo();

        return response()->json([
            'success' => true,
            'data'    => [
                'enabled'           => $enabled,
                'balance'           => $balance,
                'points_to_dollar'  => $rate,
                'dollar_value'      => LoyaltyService::pointsToDollarValue($balance),
                'total_earned'      => (int) ($stats->total_earned ?? 0),
                'total_redeemed'    => (int) ($stats->total_redeemed ?? 0),
                'expire_days'       => (int) LoyaltyService::cfg('points_expire_days', 365),
                'tier'              => $tier,
                'total_pts_earned'  => $totalEarned,
                'earn_bonus_pct'    => (int) round(($earnBonus - 1) * 100),
                'redeem_extra_pct'  => $redeemExtra,
                'next_tier'         => $nextInfo['next_tier'],
                'pts_to_next_tier'  => $nextInfo['pts_to_next'],
                'next_threshold'    => $nextInfo['next_threshold'],
                'tiers'             => $allTiers,
            ],
        ]);
    }

    // GET /rewards/history
    public function history(Request $request)
    {
        $user = $request->user();
        $page = DB::table('loyalty_points')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        $items = collect($page->items())->map(fn($r) => [
            'id'         => $r->id,
            'points'     => $r->points,
            'type'       => $r->type,
            'note'       => $r->note,
            'expires_at' => $r->expires_at,
            'created_at' => $r->created_at,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $items,
            'meta'    => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    // POST /rewards/validate-redeem
    public function validateRedeem(Request $request)
    {
        $request->validate([
            'points'      => 'required|integer|min:1',
            'order_total' => 'required|numeric|min:0.01',
        ]);

        $result = LoyaltyService::validateRedeem(
            $request->user()->id,
            (int) $request->points,
            (float) $request->order_total,
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    // POST /rewards/redeem-to-wallet — convert points → wallet balance
    public function redeemToWallet(Request $request)
    {
        $request->validate(['points' => 'required|integer|min:1']);

        $user      = $request->user();
        $requested = (int) $request->points;

        if (!LoyaltyService::isEnabled()) {
            return response()->json(['success' => false, 'message' => 'Loyalty system is disabled.'], 422);
        }

        $minPts = (int) LoyaltyService::cfg('points_payout_min_pts', 500);
        if ($requested < $minPts) {
            return response()->json(['success' => false, 'message' => "Minimum {$minPts} pts required to redeem."], 422);
        }

        $balance = LoyaltyService::balance($user->id);
        if ($requested > $balance) {
            return response()->json(['success' => false, 'message' => "Insufficient points. You have {$balance} pts."], 422);
        }

        $dollarValue = LoyaltyService::pointsToDollarValue($requested);
        if ($dollarValue <= 0) {
            return response()->json(['success' => false, 'message' => 'Points value is too low.'], 422);
        }

        // Deduct points then credit wallet
        DB::transaction(function () use ($user, $requested, $dollarValue) {
            DB::table('loyalty_points')->insert([
                'user_id'        => $user->id,
                'points'         => -$requested,
                'type'           => 'redeemed',
                'reference_type' => null,
                'reference_id'   => null,
                'note'           => "Redeemed {$requested} pts to wallet (\${$dollarValue})",
                'expires_at'     => null,
                'created_at'     => now(),
            ]);
            DB::table('users')->where('id', $user->id)->decrement('points_balance', $requested);

            $wallet = \App\Models\Wallet::getOrCreateFor('App\\Models\\User', $user->id);
            $wallet->credit($dollarValue, "Points redemption ({$requested} pts)", null, null, 'points');
        });

        $newBalance = LoyaltyService::balance($user->id);

        RewardNotificationService::pointsRedeemed($user->id, $requested, $dollarValue);

        return response()->json([
            'success'        => true,
            'message'        => "Successfully redeemed {$requested} pts for \${$dollarValue} wallet credit!",
            'data'           => [
                'points_redeemed' => $requested,
                'dollar_value'    => $dollarValue,
                'points_balance'  => $newBalance,
            ],
        ]);
    }

    // GET /rewards/earn-preview?amount=&module=
    public function earnPreview(Request $request)
    {
        $amount = (float) $request->query('amount', 0);
        $module = $request->query('module', '');
        $pts    = LoyaltyService::dollarToPoints($amount, $module, $request->user()->id);

        return response()->json([
            'success' => true,
            'data'    => ['points' => $pts, 'module' => $module, 'amount' => $amount],
        ]);
    }
}
