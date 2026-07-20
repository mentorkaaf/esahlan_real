<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoinPurchase;
use App\Models\Gift;
use App\Models\GiftTransaction;
use App\Models\LiveRoom;
use App\Models\LiveRoomReport;
use App\Models\LiveRoomViewer;
use App\Models\User;
use App\Services\FcmService;
use App\Services\RealtimeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminLiveController extends Controller
{

    // ── Dashboard ──────────────────────────────────────────────────────────────
    public function index()
    {
        $today = Carbon::today();
        $week  = Carbon::now()->subDays(7);
        $month = Carbon::now()->subDays(30);

        $stats = [
            'active_now'        => LiveRoom::where('status', 'live')->count(),
            'total_viewers_now' => LiveRoom::where('status', 'live')->sum('viewer_count'),
            'lives_today'       => LiveRoom::whereDate('created_at', $today)->count(),
            'lives_week'        => LiveRoom::where('created_at', '>=', $week)->count(),
            'lives_month'       => LiveRoom::where('created_at', '>=', $month)->count(),
            'lives_total'       => LiveRoom::count(),
            'peak_viewers_ever' => LiveRoom::max('peak_viewers') ?? 0,
            'coins_week'        => GiftTransaction::where('created_at', '>=', $week)->sum('coins_spent'),
            'coins_total'       => GiftTransaction::sum('coins_spent'),
            'gifts_sent_today'  => GiftTransaction::whereDate('created_at', $today)->sum('quantity'),
            'banned_hosts'      => User::where('banned_from_live', true)->count(),
        ];

        // Daily live count last 14 days
        $dailyLives = LiveRoom::selectRaw('DATE(created_at) as date, COUNT(*) as cnt')
            ->where('created_at', '>=', Carbon::now()->subDays(14))
            ->groupBy('date')->orderBy('date')->pluck('cnt', 'date');

        // Top hosts by peak viewers (all time)
        $topHosts = LiveRoom::with('host.communityProfile')
            ->select('host_id', DB::raw('SUM(peak_viewers) as total_views'), DB::raw('COUNT(*) as total_lives'), DB::raw('SUM(viewer_count) as current_viewers'))
            ->groupBy('host_id')
            ->orderByDesc('total_views')
            ->limit(10)
            ->get();

        // Active rooms right now
        $activeRooms = LiveRoom::with('host.communityProfile')
            ->where('status', 'live')
            ->orderByDesc('viewer_count')
            ->get();

        // Recent ended rooms
        $recentEnded = LiveRoom::with('host.communityProfile')
            ->where('status', 'ended')
            ->orderByDesc('ended_at')
            ->limit(8)
            ->get();

        // Top gifts
        $topGifts = Gift::withCount(['transactions as total_sent' => fn($q) => $q->select(DB::raw('SUM(quantity)'))])
            ->orderByDesc('total_sent')
            ->limit(5)
            ->get();

        return view('admin.live.index', compact('stats', 'dailyLives', 'topHosts', 'activeRooms', 'recentEnded', 'topGifts'));
    }

    // ── History — all live rooms ───────────────────────────────────────────────
    public function history(Request $request)
    {
        $q = LiveRoom::with('host.communityProfile');

        if ($search = $request->search) {
            $q->where(function ($sq) use ($search) {
                $sq->where('title', 'like', "%$search%")
                   ->orWhereHas('host', fn($hq) => $hq->where('name', 'like', "%$search%"));
            });
        }
        if ($status = $request->status) {
            $q->where('status', $status);
        }
        if ($from = $request->from) {
            $q->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->to) {
            $q->whereDate('created_at', '<=', $to);
        }

        $rooms = $q->orderByDesc('created_at')->paginate(25)->withQueryString();

        return view('admin.live.history', compact('rooms'));
    }

    // ── Room detail ────────────────────────────────────────────────────────────
    public function detail(int $id)
    {
        $room = LiveRoom::with(['host.communityProfile'])->findOrFail($id);

        $viewers = LiveRoomViewer::with('user.communityProfile')
            ->where('live_room_id', $id)
            ->orderByDesc('joined_at')
            ->paginate(30);

        $transactions = GiftTransaction::with(['sender.communityProfile', 'gift'])
            ->where('live_room_id', $id)
            ->orderByDesc('created_at')
            ->paginate(20);

        $giftStats = GiftTransaction::where('live_room_id', $id)
            ->select('gift_id', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(coins_spent) as coins'))
            ->with('gift')
            ->groupBy('gift_id')
            ->orderByDesc('coins')
            ->get();

        return view('admin.live.detail', compact('room', 'viewers', 'transactions', 'giftStats'));
    }

    // ── Force-end a live room ──────────────────────────────────────────────────
    public function forceEnd(int $id)
    {
        $room = LiveRoom::where('id', $id)->where('status', 'live')->firstOrFail();

        $room->update(['status' => 'ended', 'ended_at' => now()]);

        // Broadcast live.ended so all Flutter viewers/host close the screen
        RealtimeService::toPublic("live.{$id}", 'live.ended', ['room_id' => $id]);

        // Notify host
        if ($room->host?->fcm_token) {
            FcmService::sendToToken(
                $room->host->fcm_token,
                'Your live was ended by admin',
                'Your live stream has been ended by an administrator.',
                ['type' => 'live_force_ended', 'room_id' => (string) $id],
            );
        }

        return back()->with('success', "Live #{$id} force-ended.");
    }

    // ── Ban / unban user from going live ──────────────────────────────────────
    public function toggleLiveBan(int $userId)
    {
        $user = User::findOrFail($userId);
        $user->update(['banned_from_live' => !$user->banned_from_live]);

        $action = $user->banned_from_live ? 'banned from live' : 'unbanned from live';

        if ($user->banned_from_live && $user->fcm_token) {
            FcmService::sendToToken(
                $user->fcm_token,
                'Live access restricted',
                'Your live streaming access has been restricted by our team.',
                ['type' => 'live_ban']
            );
        }

        return back()->with('success', "{$user->name} {$action}.");
    }

    // ── Gifts list ─────────────────────────────────────────────────────────────
    public function gifts()
    {
        $gifts = Gift::withCount('transactions as times_sent')
            ->orderBy('sort')
            ->get();

        return view('admin.live.gifts', compact('gifts'));
    }

    public function storeGift(Request $request)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:60',
            'emoji'            => 'required|string|max:10',
            'animation'        => 'required|string|max:50',
            'animation_url'    => 'nullable|url|max:500',
            'icon_url'         => 'nullable|url|max:500',
            'coins'            => 'required|integer|min:1',
            'host_coins'       => 'nullable|integer|min:0',
            'category'         => 'required|in:normal,premium,epic,legendary,seasonal,exclusive,vip',
            'rarity'           => 'required|in:normal,rare,epic,legendary',
            'sound_effect'     => 'nullable|string|max:100',
            'sort'             => 'nullable|integer|min:0',
            'display_priority' => 'nullable|integer|min:0',
            'is_featured'      => 'nullable|boolean',
            'scheduled_start'  => 'nullable|date',
            'scheduled_end'    => 'nullable|date|after:scheduled_start',
        ]);

        Gift::create(array_merge($data, [
            'is_active'        => true,
            'sort'             => $data['sort'] ?? 0,
            'display_priority' => $data['display_priority'] ?? 0,
            'host_coins'       => $data['host_coins'] ?? (int) round($data['coins'] * 0.7),
            'is_featured'      => $data['is_featured'] ?? false,
        ]));

        return back()->with('success', 'Gift created.');
    }

    public function updateGift(Request $request, int $id)
    {
        $gift = Gift::findOrFail($id);
        $data = $request->validate([
            'name'             => 'required|string|max:60',
            'emoji'            => 'required|string|max:10',
            'animation'        => 'required|string|max:50',
            'animation_url'    => 'nullable|url|max:500',
            'icon_url'         => 'nullable|url|max:500',
            'coins'            => 'required|integer|min:1',
            'host_coins'       => 'nullable|integer|min:0',
            'category'         => 'required|in:normal,premium,epic,legendary,seasonal,exclusive,vip',
            'rarity'           => 'required|in:normal,rare,epic,legendary',
            'sound_effect'     => 'nullable|string|max:100',
            'sort'             => 'nullable|integer|min:0',
            'display_priority' => 'nullable|integer|min:0',
            'is_featured'      => 'nullable|boolean',
            'scheduled_start'  => 'nullable|date',
            'scheduled_end'    => 'nullable|date|after_or_equal:scheduled_start',
        ]);
        $gift->update($data);

        return back()->with('success', 'Gift updated.');
    }

    public function toggleGiftStatus(int $id)
    {
        $gift = Gift::findOrFail($id);
        $gift->update(['is_active' => !$gift->is_active]);

        return back()->with('success', 'Gift status toggled.');
    }

    public function destroyGift(int $id)
    {
        Gift::findOrFail($id)->delete();
        return back()->with('success', 'Gift deleted.');
    }

    // ── Coin / gift transactions ───────────────────────────────────────────────
    public function transactions(Request $request)
    {
        $q = GiftTransaction::with(['sender.communityProfile', 'receiver.communityProfile', 'gift', 'liveRoom']);

        if ($s = $request->search) {
            $q->whereHas('sender', fn($sq) => $sq->where('name', 'like', "%$s%"))
              ->orWhereHas('receiver', fn($sq) => $sq->where('name', 'like', "%$s%"));
        }
        if ($from = $request->from) $q->whereDate('created_at', '>=', $from);
        if ($to   = $request->to)   $q->whereDate('created_at', '<=', $to);

        $transactions = $q->orderByDesc('created_at')->paginate(30)->withQueryString();

        $totalCoins = GiftTransaction::sum('coins_spent');
        $totalGifts = GiftTransaction::sum('quantity');

        return view('admin.live.transactions', compact('transactions', 'totalCoins', 'totalGifts'));
    }

    // ── Reports ────────────────────────────────────────────────────────────────
    public function reports(Request $request)
    {
        $q = LiveRoomReport::with(['room.host.communityProfile', 'reporter.communityProfile'])
            ->orderByDesc('created_at');

        if ($status = $request->status) $q->where('status', $status);
        if ($reason = $request->reason) $q->where('reason', $reason);

        $reports = $q->paginate(30)->withQueryString();
        $pending = LiveRoomReport::where('status', 'pending')->count();

        return view('admin.live.reports', compact('reports', 'pending'));
    }

    public function reviewReport(Request $request, int $reportId)
    {
        $report = LiveRoomReport::findOrFail($reportId);
        $request->validate(['status' => 'required|in:reviewed,dismissed']);
        $report->update(['status' => $request->status]);
        return back()->with('success', 'Report updated.');
    }

    // ── Coin revenue ───────────────────────────────────────────────────────────
    public function coinRevenue(Request $request)
    {
        $from = $request->from ? now()->parse($request->from) : now()->subDays(30);
        $to   = $request->to   ? now()->parse($request->to)   : now();

        $summary = [
            'total_purchases'  => CoinPurchase::whereBetween('created_at', [$from, $to])->count(),
            'total_usd'        => CoinPurchase::whereBetween('created_at', [$from, $to])->sum('amount_paid'),
            'total_coins_sold' => CoinPurchase::whereBetween('created_at', [$from, $to])->sum('coins_received'),
            'coins_in_gifts'   => GiftTransaction::whereBetween('created_at', [$from, $to])->sum('coins_spent'),
        ];

        $daily = CoinPurchase::selectRaw('DATE(created_at) as date, COUNT(*) as purchases, SUM(amount_paid) as revenue, SUM(coins_received) as coins')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $topBuyers = CoinPurchase::with('user.communityProfile')
            ->selectRaw('user_id, COUNT(*) as purchases, SUM(amount_paid) as total_spent, SUM(coins_received) as total_coins')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('user_id')
            ->orderByDesc('total_spent')
            ->limit(10)
            ->get();

        $byMethod = CoinPurchase::selectRaw('payment_method, COUNT(*) as cnt, SUM(amount_paid) as total')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('payment_method')
            ->get();

        return view('admin.live.coin_revenue', compact('summary', 'daily', 'topBuyers', 'byMethod', 'from', 'to'));
    }

    // ── Live-banned users ──────────────────────────────────────────────────────
    public function banned()
    {
        $users = User::with('communityProfile')
            ->where('banned_from_live', true)
            ->orderByDesc('updated_at')
            ->paginate(30);

        return view('admin.live.banned', compact('users'));
    }
}
