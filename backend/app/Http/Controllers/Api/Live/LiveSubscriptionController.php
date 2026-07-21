<?php
namespace App\Http\Controllers\Api\Live;

use App\Http\Controllers\Controller;
use App\Models\LiveSubscription;
use App\Models\LiveSubscriptionTier;
use App\Models\LiveRoom;
use App\Models\UserCoin;
use App\Services\RealtimeService;
use Illuminate\Http\Request;

class LiveSubscriptionController extends Controller
{
    // ── GET /live/hosts/{hostId}/subscription-tiers ───────────────────────────
    public function tiers(int $hostId)
    {
        $tiers = \DB::table('live_subscription_tiers')
            ->where('host_id', $hostId)
            ->where('is_active', true)
            ->orderBy('price_usd')
            ->get();

        return response()->json(['status' => 'success', 'data' => $tiers]);
    }

    // ── POST /live/hosts/{hostId}/subscribe ───────────────────────────────────
    public function subscribe(Request $request, int $hostId)
    {
        $request->validate(['tier' => 'required|string|in:basic,supporter,superfan']);

        $tier = \DB::table('live_subscription_tiers')
            ->where('host_id', $hostId)
            ->where('tier', $request->tier)
            ->where('is_active', true)
            ->first();

        if (!$tier) {
            // Use default pricing if host has no custom tier
            $prices = ['basic' => 4.99, 'supporter' => 9.99, 'superfan' => 24.99];
            $price  = $prices[$request->tier] ?? 4.99;
        } else {
            $price = $tier->price_usd;
        }

        // Deduct coins (1 USD = 100 coins equivalent)
        $coinsNeeded = (int)($price * 100);
        if (!UserCoin::deduct(auth()->id(), $coinsNeeded)) {
            return response()->json(['status' => 'error', 'message' => 'Insufficient coins'], 402);
        }

        $sub = LiveSubscription::updateOrCreate(
            ['subscriber_id' => auth()->id(), 'host_id' => $hostId],
            [
                'tier'       => $request->tier,
                'price_usd'  => $price,
                'expires_at' => now()->addMonth(),
            ]
        );

        // Notify host
        RealtimeService::toUser($hostId, 'live.new_subscriber', [
            'subscriber_name' => auth()->user()?->name,
            'tier'            => $request->tier,
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'tier'       => $sub->tier,
                'expires_at' => $sub->expires_at->toIso8601String(),
                'new_balance' => UserCoin::where('user_id', auth()->id())->value('balance'),
            ],
        ]);
    }

    // ── GET /live/hosts/{hostId}/my-subscription ──────────────────────────────
    public function mySubscription(int $hostId)
    {
        $sub = LiveSubscription::where('subscriber_id', auth()->id())
            ->where('host_id', $hostId)
            ->first();

        if (!$sub || !$sub->isActive()) {
            return response()->json(['status' => 'success', 'data' => null]);
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'tier'       => $sub->tier,
                'expires_at' => $sub->expires_at->toIso8601String(),
            ],
        ]);
    }

    // ── POST /live/hosts/my/subscription-tiers ────────────────────────────────
    /** Host sets their own subscription tiers */
    public function setTiers(Request $request)
    {
        $request->validate([
            'tiers'               => 'required|array',
            'tiers.*.tier'        => 'required|string|in:basic,supporter,superfan',
            'tiers.*.price_usd'   => 'required|numeric|min:0.99|max:99.99',
            'tiers.*.badge_emoji' => 'sometimes|string',
            'tiers.*.badge_label' => 'sometimes|string',
            'tiers.*.perks'       => 'sometimes|array',
        ]);

        $hostId = auth()->id();
        foreach ($request->tiers as $t) {
            \DB::table('live_subscription_tiers')->updateOrInsert(
                ['host_id' => $hostId, 'tier' => $t['tier']],
                [
                    'price_usd'   => $t['price_usd'],
                    'badge_emoji' => $t['badge_emoji'] ?? '⭐',
                    'badge_label' => $t['badge_label'] ?? ucfirst($t['tier']),
                    'perks'       => json_encode($t['perks'] ?? []),
                    'is_active'   => true,
                    'updated_at'  => now(),
                    'created_at'  => now(),
                ]
            );
        }

        return response()->json(['status' => 'success']);
    }

    // ── GET /live/rooms/{roomId}/subscriber-check ─────────────────────────────
    public function roomCheck(int $roomId)
    {
        $room = LiveRoom::findOrFail($roomId);
        $sub  = LiveSubscription::where('subscriber_id', auth()->id())
            ->where('host_id', $room->host_id)
            ->first();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'is_subscriber' => $sub && $sub->isActive(),
                'tier'          => $sub?->tier,
            ],
        ]);
    }
}
