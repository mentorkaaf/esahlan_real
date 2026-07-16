<?php
namespace App\Http\Controllers\Api\Podcast;

use App\Http\Controllers\Controller;
use App\Models\Podcast;
use App\Models\PodcastCategory;
use App\Models\PodcastEpisode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PodcastSearchController extends Controller
{
    // GET /api/v1/podcast/search?q=...&type=all|podcast|episode&category=&page=1
    public function search(Request $request)
    {
        $q        = trim($request->input('q', ''));
        $type     = $request->input('type', 'all');
        $category = $request->input('category');
        $page     = (int) $request->input('page', 1);

        if (strlen($q) < 2) {
            return response()->json(['status' => 'success', 'podcasts' => [], 'episodes' => []]);
        }

        $result = [];

        if (in_array($type, ['all', 'podcast'])) {
            $podQuery = Podcast::published()
                ->where(fn($q2) => $q2->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%"))
                ->with('category:id,name,icon,color')
                ->select('id','title','slug','cover_image','category_id','total_episodes','total_followers','rating','is_verified');

            if ($category) {
                $podQuery->whereHas('category', fn($c) => $c->where('slug', $category));
            }

            $result['podcasts'] = $podQuery->orderByDesc('total_followers')->paginate(10, ['*'], 'pod_page', $page);
        }

        if (in_array($type, ['all', 'episode'])) {
            $epQuery = PodcastEpisode::published()
                ->where(fn($q2) => $q2->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%"))
                ->with('podcast:id,title,slug,cover_image,is_verified')
                ->select('id','podcast_id','title','slug','cover_image','duration','play_count','published_at');

            $result['episodes'] = $epQuery->orderByDesc('play_count')->paginate(10, ['*'], 'ep_page', $page);
        }

        return response()->json(['status' => 'success', 'query' => $q] + $result);
    }

    // GET /api/v1/podcast/recommendations
    public function recommendations(Request $request)
    {
        $userId   = auth()->id();
        $cacheKey = "podcast:rec:{$userId}";

        $data = Cache::remember($cacheKey, 300, function () use ($userId) {
            return [
                'for_you'       => $this->forYou($userId),
                'popular_today' => $this->popularToday(),
                'editors_pick'  => $this->editorsPick(),
            ];
        });

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    private function forYou(?int $userId): array
    {
        if (!$userId) return $this->popularToday();

        // Based on categories user has listened to
        $listenedCategoryIds = \DB::table('podcast_episode_plays as p')
            ->join('podcast_episodes as e', 'e.id', 'p.episode_id')
            ->join('podcasts as pod', 'pod.id', 'e.podcast_id')
            ->where('p.user_id', $userId)
            ->where('p.last_played_at', '>=', now()->subDays(30))
            ->select('pod.category_id')
            ->groupBy('pod.category_id')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(3)
            ->pluck('category_id')
            ->all();

        if (empty($listenedCategoryIds)) return $this->popularToday();

        // Podcasts in those categories that the user hasn't followed yet
        $followedIds = \DB::table('podcast_follows')->where('user_id', $userId)->pluck('podcast_id')->all();

        return Podcast::published()
            ->whereIn('category_id', $listenedCategoryIds)
            ->whereNotIn('id', $followedIds ?: [0])
            ->orderByDesc('total_followers')
            ->limit(10)
            ->get(['id','title','slug','cover_image','category_id','total_episodes','rating','is_verified'])
            ->toArray();
    }

    private function popularToday(): array
    {
        return PodcastEpisode::published()
            ->where('published_at', '>=', now()->subDays(7))
            ->orderByDesc('play_count')
            ->limit(10)
            ->with('podcast:id,title,slug,cover_image')
            ->get(['id','podcast_id','title','slug','cover_image','duration','play_count','published_at'])
            ->toArray();
    }

    private function editorsPick(): array
    {
        return Podcast::published()
            ->featured()
            ->orderByDesc('rating')
            ->limit(6)
            ->get(['id','title','slug','cover_image','total_episodes','rating','is_verified'])
            ->toArray();
    }
}
