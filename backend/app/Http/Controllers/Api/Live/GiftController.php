<?php
namespace App\Http\Controllers\Api\Live;

use App\Http\Controllers\Controller;
use App\Models\Gift;
use App\Models\GiftTransaction;
use App\Models\LiveBattle;
use App\Models\LiveBattleParticipant;
use App\Models\LiveRoom;
use App\Models\UserCoin;
use App\Services\RealtimeService;
use Illuminate\Http\Request;





class GiftController extends Controller
{
    public function __construct() {}

    /** List all available gifts */
    public function index()
    {
        $gifts = Gift::where('is_active', true)->orderBy('sort')->orderBy('coins')->get();
        return response()->json(['status' => 'success', 'data' => $gifts]);
    }

    /** Get my coin balance */
    public function balance()
    {
        $wallet = UserCoin::firstOrCreate(
            ['user_id' => auth()->id()],
            ['balance' => 0]
        );
        return response()->json(['status' => 'success', 'data' => ['balance' => $wallet->balance]]);
    }

    /** Send a gift in a live room */
    public function send(Request $request, int $roomId)
    {
        $request->validate([
            'gift_id'  => 'required|integer|exists:gifts,id',
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        $room = LiveRoom::where('id', $roomId)->where('status', 'live')->firstOrFail();
        $gift = Gift::where('id', $request->gift_id)->where('is_active', true)->firstOrFail();

        $totalCoins = $gift->coins * $request->quantity;
        $senderId   = auth()->id();

        if ($senderId === $room->host_id) {
            return response()->json(['status' => 'error', 'message' => 'Host cannot send gifts to self'], 422);
        }

        // Deduct coins
        if (!UserCoin::deduct($senderId, $totalCoins)) {
            return response()->json(['status' => 'error', 'message' => 'Insufficient coins'], 402);
        }

        // Record transaction + update room coin total
        $tx = GiftTransaction::create([
            'sender_id'    => $senderId,
            'receiver_id'  => $room->host_id,
            'gift_id'      => $gift->id,
            'live_room_id' => $roomId,
            'quantity'     => $request->quantity,
            'coins_spent'  => $totalCoins,
        ]);

        $room->increment('total_coins_earned', $totalCoins);

        // Update battle score if an active battle exists for this room
        $this->updateBattleScore($roomId, $totalCoins);

        // Update live goal (coins + gifts)
        LiveGoalController::increment($roomId, 'coins', $totalCoins);
        LiveGoalController::increment($roomId, 'gifts', $request->quantity);

        $sender = auth()->user();
        $p      = $sender?->communityProfile;

        // Broadcast gift event to room (for animations)
        RealtimeService::toPublic("live.{$roomId}", 'gift.received', [
            'gift'     => $gift->toArray(),
            'quantity' => $request->quantity,
            'sender'   => [
                'id'     => $senderId,
                'name'   => $sender?->name,
                'avatar' => $p?->avatar ?? '',
            ],
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'transaction_id' => $tx->id,
                'coins_spent'    => $totalCoins,
                'new_balance'    => UserCoin::where('user_id', $senderId)->value('balance'),
            ],
        ]);
    }

    /** Update battle score when a gift is sent */
    private function updateBattleScore(int $roomId, int $coins): void
    {
        $participant = LiveBattleParticipant::where('live_room_id', $roomId)
            ->whereHas('battle', fn($q) => $q->where('status', 'active'))
            ->with('battle')
            ->first();

        if (!$participant) return;

        $participant->increment('score', $coins);

        $battle = $participant->battle->fresh(['participants.host.communityProfile']);

        // Recalculate ranks
        $ranked = $battle->participants->sortByDesc('score')->values();
        foreach ($ranked as $i => $p) {
            $p->update(['rank' => $i + 1]);
        }

        $scores = $ranked->map(fn($p, $i) => [
            'host_id'      => $p->host_id,
            'live_room_id' => $p->live_room_id,
            'name'         => $p->host?->name ?? '',
            'score'        => $p->fresh()->score,
            'rank'         => $i + 1,
        ])->values()->all();

        // Broadcast to ALL participant rooms in real-time
        foreach ($battle->participants as $p) {
            RealtimeService::toPublic("live.{$p->live_room_id}", 'battle.score_updated', [
                'battle_id' => $battle->id,
                'scores'    => $scores,
                'ends_at'   => $battle->ends_at?->toIso8601String(),
            ]);
        }
    }

    /** Top gifts in a live room */
    public function topGifters(int $roomId)
    {
        $top = GiftTransaction::with('sender.communityProfile')
            ->where('live_room_id', $roomId)
            ->selectRaw('sender_id, SUM(coins_spent) as total_coins')
            ->groupBy('sender_id')
            ->orderByDesc('total_coins')
            ->limit(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $top->map(fn($t) => [
                'user'        => [
                    'id'     => $t->sender?->id,
                    'name'   => $t->sender?->name,
                    'avatar' => $t->sender?->communityProfile?->avatar ?? '',
                ],
                'total_coins' => $t->total_coins,
            ]),
        ]);
    }
}
