<?php
namespace App\Http\Controllers\Api\Live;

use App\Http\Controllers\Controller;
use App\Models\GiftTransaction;
use App\Models\LiveRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LiveLeaderboardController extends Controller
{
    /** GET /live/rooms/{id}/leaderboard?period=daily|weekly|monthly|all */
    public function room(int $id)
    {
        $room = LiveRoom::findOrFail($id);
        $period = request('period', 'all');

        $cacheKey = "live_lb:room:{$id}:{$period}";
        $data = Cache::remember($cacheKey, 30, fn() => $this->buildRoomLeaderboard($id, $period));

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /** GET /live/leaderboard/global?period=daily|weekly|monthly|all */
    public function global()
    {
        $period = request('period', 'daily');

        $cacheKey = "live_lb:global:{$period}";
        $data = Cache::remember($cacheKey, 60, fn() => $this->buildGlobalLeaderboard($period));

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    private function buildRoomLeaderboard(int $roomId, string $period): array
    {
        $query = GiftTransaction::where('live_room_id', $roomId);
        $query = $this->applyPeriod($query, $period);

        $rows = $query
            ->select('sender_id', DB::raw('SUM(coins_spent) as total_coins'), DB::raw('SUM(quantity) as total_gifts'))
            ->with('sender.communityProfile')
            ->groupBy('sender_id')
            ->orderByDesc('total_coins')
            ->limit(50)
            ->get();

        return $rows->values()->map(fn($r, $i) => [
            'rank'        => $i + 1,
            'user_id'     => $r->sender_id,
            'name'        => $r->sender?->name ?? '',
            'username'    => $r->sender?->communityProfile?->username ?? '',
            'avatar'      => $r->sender?->communityProfile?->avatar ?? '',
            'total_coins' => (int) $r->total_coins,
            'total_gifts' => (int) $r->total_gifts,
        ])->all();
    }

    private function buildGlobalLeaderboard(string $period): array
    {
        $query = GiftTransaction::query();
        $query = $this->applyPeriod($query, $period);

        // Top senders globally
        $senders = $query->clone()
            ->select('sender_id', DB::raw('SUM(coins_spent) as total_coins'))
            ->with('sender.communityProfile')
            ->groupBy('sender_id')
            ->orderByDesc('total_coins')
            ->limit(20)
            ->get()
            ->values()
            ->map(fn($r, $i) => [
                'rank'        => $i + 1,
                'user_id'     => $r->sender_id,
                'name'        => $r->sender?->name ?? '',
                'username'    => $r->sender?->communityProfile?->username ?? '',
                'avatar'      => $r->sender?->communityProfile?->avatar ?? '',
                'total_coins' => (int) $r->total_coins,
            ])->all();

        // Top receiving hosts
        $hosts = $query->clone()
            ->select('receiver_id', DB::raw('SUM(coins_spent) as earned_coins'))
            ->with('receiver.communityProfile')
            ->groupBy('receiver_id')
            ->orderByDesc('earned_coins')
            ->limit(20)
            ->get()
            ->values()
            ->map(fn($r, $i) => [
                'rank'         => $i + 1,
                'user_id'      => $r->receiver_id,
                'name'         => $r->receiver?->name ?? '',
                'username'     => $r->receiver?->communityProfile?->username ?? '',
                'avatar'       => $r->receiver?->communityProfile?->avatar ?? '',
                'earned_coins' => (int) $r->earned_coins,
            ])->all();

        return ['top_gifters' => $senders, 'top_hosts' => $hosts];
    }

    private function applyPeriod($query, string $period)
    {
        return match ($period) {
            'daily'   => $query->whereDate('created_at', today()),
            'weekly'  => $query->where('created_at', '>=', Carbon::now()->startOfWeek()),
            'monthly' => $query->where('created_at', '>=', Carbon::now()->startOfMonth()),
            default   => $query,
        };
    }
}
