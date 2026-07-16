<?php
namespace App\Http\Controllers\Api\Podcast;

use App\Http\Controllers\Controller;
use App\Models\Podcast;
use App\Models\PodcastCategory;
use App\Models\PodcastEpisode;
use App\Models\PodcastEpisodePlay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PodcastHomeController extends Controller
{
    // GET /api/v1/podcast/home  — full home data
    public function home(Request $request)
    {
        $userId = auth()->id();

        $stats = Cache::remember('podcast:stats', 300, fn() => [
            'total_podcasts'  => Podcast::published()->count(),
            'total_categories'=> PodcastCategory::where('is_active', true)->count(),
            'total_episodes'  => PodcastEpisode::published()->count(),
            'new_this_week'   => PodcastEpisode::published()->where('published_at', '>=', now()->subWeek())->count(),
            'live_rooms'      => DB::table('podcast_live_rooms')->where('status','live')->count(),
        ]);

        $userId = auth()->id();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'stats'              => $stats,
                'hero_featured'      => $this->heroFeatured(),
                'continue_listening' => $this->continueListening($userId),
                'popular_podcasts'   => $this->popularPodcasts(),
                'new_releases'       => $this->newReleases(),
                'trending_today'     => $this->trendingToday(),
                'recommended'        => $this->recommended($userId),
                'top_charts'         => $this->topChartsHome(),
                'live_rooms'         => $this->liveRooms(),
                'categories'         => $this->featuredCategories(),
                'verified_creators'  => $this->verifiedCreators(),
            ],
        ]);
    }

    // GET /api/v1/podcast/top-charts
    public function topCharts(Request $request)
    {
        $type = $request->input('type', 'popular'); // popular | new | trending

        $query = PodcastEpisode::published()
            ->with('podcast:id,title,slug,cover_image,is_verified');

        if ($type === 'new') {
            $query->orderByDesc('published_at');
        } elseif ($type === 'trending') {
            $query->where('published_at', '>=', now()->subDays(7))->orderByDesc('play_count');
        } else {
            $query->orderByDesc('play_count');
        }

        $episodes = $query->select('id','podcast_id','title','slug','cover_image','duration','play_count','like_count','published_at')
            ->paginate(20);

        return response()->json(['status' => 'success', 'data' => $episodes]);
    }

    // GET /api/v1/podcast/live-rooms
    public function liveRooms(Request $request = null)
    {
        $rooms = DB::table('podcast_live_rooms as r')
            ->join('users as u', 'u.id', 'r.host_id')
            ->where('r.status', 'live')
            ->select('r.*', 'u.name as host_name', 'u.profile_photo_path as host_avatar')
            ->orderByDesc('r.listener_count')
            ->limit(10)
            ->get();

        if ($request === null) return $rooms->toArray();

        return response()->json(['status' => 'success', 'data' => $rooms]);
    }

    // GET /api/v1/podcast/stats
    public function stats()
    {
        return response()->json([
            'status' => 'success',
            'data'   => Cache::remember('podcast:stats', 300, fn() => [
                'total_podcasts'  => Podcast::published()->count(),
                'total_categories'=> PodcastCategory::where('is_active', true)->count(),
                'new_releases'    => PodcastEpisode::published()->where('published_at', '>=', now()->subWeek())->count(),
                'live_rooms'      => DB::table('podcast_live_rooms')->where('status','live')->count(),
            ]),
        ]);
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function heroFeatured(): array
    {
        return Podcast::published()->featured()
            ->with(['category:id,name,icon,color'])
            ->select('id','title','slug','description','cover_image','category_id','total_episodes','total_followers','rating','is_verified')
            ->orderByDesc('total_followers')
            ->limit(5)
            ->get()
            ->map(fn($p) => $this->podcastData($p))
            ->toArray();
    }

    private function continueListening(?int $userId): array
    {
        if (!$userId) return [];

        return PodcastEpisodePlay::where('user_id', $userId)
            ->where('completed', false)
            ->where('position', '>', 10)
            ->with(['episode:id,podcast_id,title,slug,cover_image,duration,audio_url',
                    'episode.podcast:id,title,slug,cover_image'])
            ->orderByDesc('last_played_at')
            ->limit(8)
            ->get()
            ->filter(fn($p) => $p->episode)
            ->map(fn($play) => [
                'episode'  => $this->episodeData($play->episode),
                'position' => $play->position,
                'progress' => $play->episode->duration > 0
                    ? round($play->position / $play->episode->duration * 100)
                    : 0,
            ])
            ->values()
            ->toArray();
    }

    private function popularPodcasts(): array
    {
        return Podcast::published()
            ->with('category:id,name,icon,color')
            ->select('id','title','slug','cover_image','category_id','total_episodes','total_followers','total_plays','rating','is_verified')
            ->orderByDesc('total_followers')
            ->limit(12)
            ->get()
            ->map(fn($p) => $this->podcastData($p))
            ->toArray();
    }

    private function newReleases(): array
    {
        return PodcastEpisode::published()
            ->with('podcast:id,title,slug,cover_image,is_verified')
            ->select('id','podcast_id','title','slug','cover_image','duration','play_count','published_at')
            ->orderByDesc('published_at')
            ->limit(12)
            ->get()
            ->map(fn($e) => $this->episodeData($e))
            ->toArray();
    }

    private function trendingToday(): array
    {
        return PodcastEpisode::published()
            ->where('published_at', '>=', now()->subDays(3))
            ->with('podcast:id,title,slug,cover_image,is_verified')
            ->select('id','podcast_id','title','slug','cover_image','duration','play_count','like_count','published_at')
            ->orderByDesc('play_count')
            ->limit(12)
            ->get()
            ->map(fn($e) => $this->episodeData($e))
            ->toArray();
    }

    private function recommended(?int $userId): array
    {
        if (!$userId) return $this->popularPodcasts();

        $catIds = DB::table('podcast_episode_plays as p')
            ->join('podcast_episodes as e', 'e.id', 'p.episode_id')
            ->join('podcasts as pod', 'pod.id', 'e.podcast_id')
            ->where('p.user_id', $userId)
            ->where('p.last_played_at', '>=', now()->subDays(30))
            ->select('pod.category_id')
            ->groupBy('pod.category_id')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(4)
            ->pluck('category_id')
            ->all();

        if (empty($catIds)) return $this->popularPodcasts();

        $followedIds = DB::table('podcast_follows')->where('user_id', $userId)->pluck('podcast_id')->all();

        return Podcast::published()
            ->whereIn('category_id', $catIds)
            ->whereNotIn('id', $followedIds ?: [0])
            ->with('category:id,name,icon,color')
            ->select('id','title','slug','cover_image','category_id','total_episodes','total_followers','rating','is_verified')
            ->orderByDesc('total_followers')
            ->limit(12)
            ->get()
            ->map(fn($p) => $this->podcastData($p))
            ->toArray();
    }

    private function topChartsHome(): array
    {
        return PodcastEpisode::published()
            ->with('podcast:id,title,slug,cover_image,is_verified')
            ->select('id','podcast_id','title','slug','cover_image','duration','play_count','published_at')
            ->orderByDesc('play_count')
            ->limit(5)
            ->get()
            ->map(fn($e) => $this->episodeData($e))
            ->toArray();
    }

    private function featuredCategories(): array
    {
        return PodcastCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->select('id','name','slug','icon','color','podcast_count')
            ->get()
            ->toArray();
    }

    private function verifiedCreators(): array
    {
        return Podcast::published()
            ->where('is_verified', true)
            ->with('user:id,name,profile_photo_path')
            ->select('id','title','slug','cover_image','user_id','total_followers','is_verified')
            ->orderByDesc('total_followers')
            ->limit(10)
            ->get()
            ->map(fn($p) => [
                'id'            => $p->id,
                'title'         => $p->title,
                'slug'          => $p->slug,
                'cover_image'   => $this->media($p->cover_image),
                'user'          => $p->user ? ['name' => $p->user->name, 'avatar' => $this->media($p->user->profile_photo_path)] : null,
                'followers'     => $p->total_followers,
                'is_verified'   => true,
            ])
            ->toArray();
    }

    private function podcastData(Podcast $p): array
    {
        return [
            'id'              => $p->id,
            'title'           => $p->title,
            'slug'            => $p->slug,
            'description'     => $p->description,
            'cover_image'     => $this->media($p->cover_image),
            'category'        => $p->category ? ['name' => $p->category->name, 'icon' => $p->category->icon, 'color' => $p->category->color] : null,
            'total_episodes'  => $p->total_episodes,
            'total_followers' => $p->total_followers,
            'total_plays'     => $p->total_plays ?? 0,
            'rating'          => round($p->rating, 1),
            'is_verified'     => $p->is_verified,
        ];
    }

    private function episodeData(PodcastEpisode $e): array
    {
        return [
            'id'           => $e->id,
            'title'        => $e->title,
            'slug'         => $e->slug,
            'cover_image'  => $this->media($e->cover_image ?? $e->podcast?->cover_image),
            'duration'     => $e->duration,
            'duration_fmt' => $e->duration_formatted,
            'play_count'   => $e->play_count,
            'like_count'   => $e->like_count ?? 0,
            'audio_url'    => $e->audio_url ? $this->media($e->audio_url) : null,
            'published_at' => $e->published_at?->toISOString(),
            'podcast'      => $e->podcast ? [
                'id'         => $e->podcast->id,
                'title'      => $e->podcast->title,
                'cover_image'=> $this->media($e->podcast->cover_image),
                'is_verified'=> $e->podcast->is_verified,
            ] : null,
        ];
    }

    private function media(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http')) return $path;
        return url('/api/v1/media?f=' . ltrim($path, '/'));
    }
}
