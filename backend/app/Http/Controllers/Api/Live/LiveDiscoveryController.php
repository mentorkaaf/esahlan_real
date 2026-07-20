<?php
namespace App\Http\Controllers\Api\Live;

use App\Http\Controllers\Controller;
use App\Models\CommunityFollow;
use App\Models\GiftTransaction;
use App\Models\LiveRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LiveDiscoveryController extends Controller
{
    private const CATEGORIES = [
        'general'    => ['label' => 'All',        'emoji' => '🌐'],
        'gaming'     => ['label' => 'Gaming',     'emoji' => '🎮'],
        'music'      => ['label' => 'Music',      'emoji' => '🎵'],
        'cooking'    => ['label' => 'Cooking',    'emoji' => '🍳'],
        'education'  => ['label' => 'Education',  'emoji' => '📚'],
        'sports'     => ['label' => 'Sports',     'emoji' => '⚽'],
        'beauty'     => ['label' => 'Beauty',     'emoji' => '💄'],
        'fitness'    => ['label' => 'Fitness',    'emoji' => '💪'],
        'travel'     => ['label' => 'Travel',     'emoji' => '✈️'],
        'comedy'     => ['label' => 'Comedy',     'emoji' => '😂'],
        'art'        => ['label' => 'Art',        'emoji' => '🎨'],
        'news'       => ['label' => 'News',       'emoji' => '📰'],
        'technology' => ['label' => 'Tech',       'emoji' => '💻'],
        'fashion'    => ['label' => 'Fashion',    'emoji' => '👗'],
        'finance'    => ['label' => 'Finance',    'emoji' => '💰'],
    ];

    /** GET /v1/live/discovery — full discovery feed */
    public function index(Request $request)
    {
        $userId   = auth()->id();
        $category = $request->input('category', 'general');
        $search   = $request->input('search', '');

        $cacheKey = "live:discovery:{$userId}:{$category}:" . md5($search);

        $data = Cache::remember($cacheKey, 30, function () use ($userId, $category, $search) {
            return [
                'featured'    => $this->featured($category, $search),
                'following'   => $this->following($userId, $category),
                'trending'    => $this->trending($category, $search),
                'all'         => $this->allRooms($category, $search),
            ];
        });

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /** GET /v1/live/discovery/categories */
    public function categories()
    {
        $counts = LiveRoom::where('status', 'live')
            ->select('category', DB::raw('count(*) as cnt'))
            ->groupBy('category')
            ->pluck('cnt', 'category');

        $cats = [];
        foreach (self::CATEGORIES as $key => $meta) {
            $cats[] = [
                'key'   => $key,
                'label' => $meta['label'],
                'emoji' => $meta['emoji'],
                'count' => $counts[$key] ?? 0,
            ];
        }

        // Sort: general first, then by count
        usort($cats, fn($a, $b) =>
            $a['key'] === 'general' ? -1 : ($b['key'] === 'general' ? 1 : $b['count'] - $a['count'])
        );

        return response()->json(['status' => 'success', 'data' => $cats]);
    }

    /** GET /v1/live/discovery/recommended */
    public function recommended()
    {
        $userId = auth()->id();

        // Hosts the user has watched before (past 30 days)
        $watchedHostIds = DB::table('live_room_viewers')
            ->join('live_rooms', 'live_rooms.id', '=', 'live_room_viewers.live_room_id')
            ->where('live_room_viewers.user_id', $userId)
            ->where('live_room_viewers.created_at', '>=', now()->subDays(30))
            ->pluck('live_rooms.host_id')
            ->unique()
            ->toArray();

        // Hosts followed by the people the user follows
        $followingIds = CommunityFollow::where('follower_id', $userId)->pluck('following_id')->toArray();
        $friendFollowedHostIds = $followingIds
            ? CommunityFollow::whereIn('follower_id', $followingIds)
                ->pluck('following_id')
                ->unique()
                ->toArray()
            : [];

        $interestHostIds = array_unique(array_merge($watchedHostIds, $friendFollowedHostIds));

        $rooms = LiveRoom::with('host.communityProfile')
            ->where('status', 'live')
            ->when(!empty($interestHostIds), fn($q) =>
                $q->whereIn('host_id', $interestHostIds)
                  ->orWhere(fn($q2) => $q2->where('viewer_count', '>', 50))
            )
            ->orderByDesc('viewer_count')
            ->limit(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $rooms->map(fn($r) => $this->transformRoom($r)),
        ]);
    }

    /** GET /v1/live/discovery/search?q= */
    public function search(Request $request)
    {
        $q = $request->input('q', '');
        if (strlen($q) < 2) {
            return response()->json(['status' => 'success', 'data' => []]);
        }

        $rooms = LiveRoom::with('host.communityProfile')
            ->where('status', 'live')
            ->where(function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                      ->orWhereHas('host', fn($u) => $u->where('name', 'like', "%{$q}%"));
            })
            ->orderByDesc('viewer_count')
            ->limit(20)
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $rooms->map(fn($r) => $this->transformRoom($r)),
        ]);
    }

    // ─── Private helpers ────────────────────────────────────────────────────────

    private function featured(string $category, string $search): ?array
    {
        $q = LiveRoom::with('host.communityProfile')
            ->where('status', 'live')
            ->when($category !== 'general', fn($q) => $q->where('category', $category))
            ->when($search, fn($q) => $q->where('title', 'like', "%{$search}%"))
            ->orderByDesc('viewer_count');

        $room = $q->first();
        return $room ? $this->transformRoom($room) : null;
    }

    private function following(int $userId, string $category): array
    {
        $followingIds = CommunityFollow::where('follower_id', $userId)->pluck('following_id');
        if ($followingIds->isEmpty()) return [];

        return LiveRoom::with('host.communityProfile')
            ->where('status', 'live')
            ->whereIn('host_id', $followingIds)
            ->when($category !== 'general', fn($q) => $q->where('category', $category))
            ->orderByDesc('viewer_count')
            ->limit(10)
            ->get()
            ->map(fn($r) => $this->transformRoom($r))
            ->values()
            ->toArray();
    }

    private function trending(string $category, string $search): array
    {
        return LiveRoom::with('host.communityProfile')
            ->where('status', 'live')
            ->where('viewer_count', '>=', 10)
            ->when($category !== 'general', fn($q) => $q->where('category', $category))
            ->when($search, fn($q) => $q->where('title', 'like', "%{$search}%"))
            ->orderByDesc('viewer_count')
            ->limit(8)
            ->get()
            ->map(fn($r) => $this->transformRoom($r))
            ->values()
            ->toArray();
    }

    private function allRooms(string $category, string $search): array
    {
        return LiveRoom::with('host.communityProfile')
            ->where('status', 'live')
            ->when($category !== 'general', fn($q) => $q->where('category', $category))
            ->when($search, fn($q) => $q->where('title', 'like', "%{$search}%"))
            ->orderByDesc('viewer_count')
            ->limit(40)
            ->get()
            ->map(fn($r) => $this->transformRoom($r))
            ->values()
            ->toArray();
    }

    private function transformRoom(LiveRoom $room): array
    {
        $host = $room->host;
        $p    = $host?->communityProfile;
        return [
            'id'           => $room->id,
            'title'        => $room->title,
            'room_name'    => $room->room_name,
            'thumbnail'    => $room->thumbnail,
            'category'     => $room->category ?? 'general',
            'tags'         => $room->tags ?? [],
            'status'       => $room->status,
            'viewer_count' => $room->viewer_count,
            'peak_viewers' => $room->peak_viewers,
            'host' => [
                'id'       => $host?->id,
                'name'     => $host?->name ?? '',
                'username' => $p?->username ?? '',
                'avatar'   => $p?->avatar ?? '',
            ],
            'created_at' => $room->created_at,
        ];
    }
}
