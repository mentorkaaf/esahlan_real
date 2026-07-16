<?php
namespace App\Http\Controllers\Api\Podcast;

use App\Http\Controllers\Controller;
use App\Models\Podcast;
use App\Models\PodcastCategory;
use App\Models\PodcastEpisode;
use App\Models\PodcastEpisodePlay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PodcastFeedController extends Controller
{
    // GET /api/v1/podcast/home
    public function home(Request $request)
    {
        $userId = auth()->id();
        $cacheKey = "podcast:home:{$userId}";

        $data = Cache::remember($cacheKey, 120, function () use ($userId) {
            return [
                'featured'          => $this->getFeatured(),
                'categories'        => $this->getCategories(),
                'trending_episodes' => $this->getTrendingEpisodes($userId),
                'new_podcasts'      => $this->getNewPodcasts(),
                'continue_listening'=> $this->getContinueListening($userId),
                'following_updates' => $this->getFollowingUpdates($userId),
            ];
        });

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    private function getFeatured(): array
    {
        return Podcast::published()->featured()
            ->with(['category:id,name,icon,color', 'user:id,name,avatar'])
            ->select('id','title','slug','cover_image','description','category_id','user_id',
                     'total_episodes','total_followers','rating','is_verified')
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn($p) => $this->transformPodcast($p))
            ->toArray();
    }

    private function getCategories(): array
    {
        return PodcastCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->select('id','name','slug','icon','color','podcast_count')
            ->get()
            ->toArray();
    }

    private function getTrendingEpisodes(?int $userId): array
    {
        $likedIds   = $userId ? \DB::table('podcast_likes')->where('user_id',$userId)->pluck('episode_id')->all() : [];
        $savedIds   = $userId ? \DB::table('podcast_saves')->where('user_id',$userId)->pluck('episode_id')->all() : [];

        return PodcastEpisode::published()
            ->with(['podcast:id,title,slug,cover_image,is_verified'])
            ->select('id','podcast_id','title','slug','cover_image','duration','play_count','like_count','published_at')
            ->orderByDesc('play_count')
            ->limit(10)
            ->get()
            ->map(fn($e) => $this->transformEpisode($e, $likedIds, $savedIds, null))
            ->toArray();
    }

    private function getNewPodcasts(): array
    {
        return Podcast::published()
            ->with(['category:id,name,icon,color'])
            ->select('id','title','slug','cover_image','category_id','total_episodes','rating','is_verified')
            ->latest('published_at')
            ->limit(8)
            ->get()
            ->map(fn($p) => $this->transformPodcast($p))
            ->toArray();
    }

    private function getContinueListening(?int $userId): array
    {
        if (!$userId) return [];

        return PodcastEpisodePlay::where('user_id', $userId)
            ->where('completed', false)
            ->where('position', '>', 10)
            ->with(['episode' => fn($q) => $q->select('id','podcast_id','title','slug','cover_image','duration','audio_url')
                ->with('podcast:id,title,slug,cover_image')])
            ->orderByDesc('last_played_at')
            ->limit(5)
            ->get()
            ->filter(fn($p) => $p->episode)
            ->map(fn($play) => [
                'episode'   => $this->transformEpisode($play->episode, [], [], $play),
                'position'  => $play->position,
                'progress'  => $play->episode->duration > 0
                    ? round($play->position / $play->episode->duration * 100)
                    : 0,
            ])
            ->values()
            ->toArray();
    }

    private function getFollowingUpdates(?int $userId): array
    {
        if (!$userId) return [];

        $followedPodcastIds = \DB::table('podcast_follows')
            ->where('user_id', $userId)
            ->pluck('podcast_id')
            ->all();

        if (empty($followedPodcastIds)) return [];

        return PodcastEpisode::published()
            ->whereIn('podcast_id', $followedPodcastIds)
            ->with(['podcast:id,title,slug,cover_image,is_verified'])
            ->select('id','podcast_id','title','slug','cover_image','duration','play_count','published_at')
            ->orderByDesc('published_at')
            ->limit(10)
            ->get()
            ->map(fn($e) => $this->transformEpisode($e, [], [], null))
            ->toArray();
    }

    private function transformPodcast(Podcast $p): array
    {
        return [
            'id'               => $p->id,
            'title'            => $p->title,
            'slug'             => $p->slug,
            'cover_image'      => $this->mediaUrl($p->cover_image),
            'category'         => $p->category ? ['name' => $p->category->name, 'icon' => $p->category->icon, 'color' => $p->category->color] : null,
            'total_episodes'   => $p->total_episodes,
            'total_followers'  => $p->total_followers,
            'rating'           => round($p->rating, 1),
            'is_verified'      => $p->is_verified,
            'description'      => $p->description,
        ];
    }

    private function transformEpisode(PodcastEpisode $e, array $likedIds, array $savedIds, ?PodcastEpisodePlay $play): array
    {
        return [
            'id'              => $e->id,
            'title'           => $e->title,
            'slug'            => $e->slug,
            'cover_image'     => $this->mediaUrl($e->cover_image ?? $e->podcast?->cover_image),
            'duration'        => $e->duration,
            'duration_fmt'    => $e->duration_formatted,
            'play_count'      => $e->play_count,
            'like_count'      => $e->like_count,
            'audio_url'       => $e->audio_url ? url('/api/v1/media?f=' . ltrim($e->audio_url, '/')) : null,
            'podcast'         => $e->podcast ? ['id'=>$e->podcast->id,'title'=>$e->podcast->title,'cover_image'=>$this->mediaUrl($e->podcast->cover_image),'is_verified'=>$e->podcast->is_verified] : null,
            'published_at'    => $e->published_at?->toISOString(),
            'is_liked'        => in_array($e->id, $likedIds),
            'is_saved'        => in_array($e->id, $savedIds),
            'resume_position' => $play?->position ?? 0,
        ];
    }

    private function mediaUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http')) return $path;
        return url('/api/v1/media?f=' . ltrim($path, '/'));
    }
}
