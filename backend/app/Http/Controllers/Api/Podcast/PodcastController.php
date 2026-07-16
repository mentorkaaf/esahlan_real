<?php
namespace App\Http\Controllers\Api\Podcast;

use App\Http\Controllers\Controller;
use App\Models\Podcast;
use App\Models\PodcastFollow;
use Illuminate\Http\Request;

class PodcastController extends Controller
{
    // GET /api/v1/podcast/shows
    public function index(Request $request)
    {
        $category = $request->input('category');
        $sort     = $request->input('sort', 'popular'); // popular|new|rating

        $query = Podcast::published()->with('category:id,name,icon,color')
            ->select('id','title','slug','cover_image','category_id','total_episodes','total_followers','rating','is_verified');

        if ($category) {
            $query->whereHas('category', fn($q) => $q->where('slug', $category));
        }

        match($sort) {
            'new'    => $query->orderByDesc('published_at'),
            'rating' => $query->orderByDesc('rating'),
            default  => $query->orderByDesc('total_followers'),
        };

        return response()->json(['status' => 'success', 'data' => $query->paginate(20)]);
    }

    // GET /api/v1/podcast/shows/{slug}
    public function show(Request $request, string $slug)
    {
        $userId = auth()->id();

        $podcast = Podcast::published()
            ->where('slug', $slug)
            ->with(['category:id,name,icon,color','user:id,name,avatar'])
            ->firstOrFail();

        $episodes = $podcast->episodes()
            ->select('id','podcast_id','title','slug','cover_image','duration','play_count','like_count','published_at','episode_number','season')
            ->paginate(20);

        return response()->json([
            'status'      => 'success',
            'podcast'     => array_merge($podcast->toArray(), [
                'cover_image'   => $this->mediaUrl($podcast->cover_image),
                'is_following'  => $podcast->isFollowedByUser($userId),
            ]),
            'episodes'    => $episodes,
        ]);
    }

    // POST /api/v1/podcast/shows/{id}/follow
    public function follow(Request $request, int $id)
    {
        $userId = auth()->id();
        $podcast = Podcast::published()->findOrFail($id);

        $existing = PodcastFollow::where('user_id',$userId)->where('podcast_id',$id)->first();

        if ($existing) {
            $existing->delete();
            $podcast->decrement('total_followers');
            $following = false;
        } else {
            PodcastFollow::create(['user_id'=>$userId,'podcast_id'=>$id,'notify_new_episodes'=>true]);
            $podcast->increment('total_followers');
            $following = true;
        }

        return response()->json(['status'=>'success','following'=>$following,'total_followers'=>$podcast->total_followers]);
    }

    // GET /api/v1/podcast/following
    public function following(Request $request)
    {
        $userId = auth()->id();

        $podcasts = Podcast::published()
            ->whereHas('follows', fn($q) => $q->where('user_id', $userId))
            ->select('id','title','slug','cover_image','total_episodes','total_followers','rating','is_verified')
            ->orderByDesc('updated_at')
            ->paginate(20);

        return response()->json(['status'=>'success','data'=>$podcasts]);
    }

    private function mediaUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http')) return $path;
        return url('/api/v1/media?f=' . ltrim($path, '/'));
    }
}
