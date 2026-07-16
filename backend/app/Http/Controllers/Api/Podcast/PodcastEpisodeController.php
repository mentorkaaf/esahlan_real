<?php
namespace App\Http\Controllers\Api\Podcast;

use App\Http\Controllers\Controller;
use App\Models\PodcastEpisode;
use App\Models\PodcastEpisodePlay;
use App\Models\PodcastLike;
use App\Models\PodcastSave;
use Illuminate\Http\Request;

class PodcastEpisodeController extends Controller
{
    // GET /api/v1/podcast/episodes/{slug}
    public function show(Request $request, string $slug)
    {
        $userId = auth()->id();

        $episode = PodcastEpisode::published()
            ->where('slug', $slug)
            ->with(['podcast:id,title,slug,cover_image,is_verified,user_id'])
            ->firstOrFail();

        $play = $userId ? $episode->userPlay($userId) : null;

        return response()->json([
            'status'  => 'success',
            'episode' => array_merge($episode->toArray(), [
                'cover_image'     => $this->mediaUrl($episode->cover_image ?? $episode->podcast?->cover_image),
                'audio_url'       => $this->mediaUrl($episode->audio_url),
                'duration_fmt'    => $episode->duration_formatted,
                'is_liked'        => $episode->isLikedByUser($userId),
                'is_saved'        => $episode->isSavedByUser($userId),
                'resume_position' => $play?->position ?? 0,
            ]),
        ]);
    }

    // POST /api/v1/podcast/episodes/{id}/play
    public function recordPlay(Request $request, int $id)
    {
        $userId  = auth()->id();
        $episode = PodcastEpisode::published()->findOrFail($id);

        $validated = $request->validate([
            'position'  => 'required|integer|min:0',
            'completed' => 'boolean',
        ]);

        if ($userId) {
            PodcastEpisodePlay::updateOrCreate(
                ['user_id'=>$userId,'episode_id'=>$id],
                [
                    'position'       => $validated['position'],
                    'completed'      => $validated['completed'] ?? false,
                    'last_played_at' => now(),
                    'play_count'     => \DB::raw('play_count + 1'),
                ]
            );
        }

        // Increment global play count once per session (first call with position=0)
        if ($validated['position'] === 0) {
            $episode->increment('play_count');
            $episode->podcast?->increment('total_plays');
        }

        return response()->json(['status'=>'success']);
    }

    // POST /api/v1/podcast/episodes/{id}/like
    public function like(Request $request, int $id)
    {
        $userId  = auth()->id();
        $episode = PodcastEpisode::published()->findOrFail($id);

        $existing = PodcastLike::where('user_id',$userId)->where('episode_id',$id)->first();

        if ($existing) {
            $existing->delete();
            $episode->decrement('like_count');
            $liked = false;
        } else {
            PodcastLike::create(['user_id'=>$userId,'episode_id'=>$id]);
            $episode->increment('like_count');
            $liked = true;
        }

        return response()->json(['status'=>'success','liked'=>$liked,'like_count'=>$episode->like_count]);
    }

    // POST /api/v1/podcast/episodes/{id}/save
    public function save(Request $request, int $id)
    {
        $userId  = auth()->id();
        $episode = PodcastEpisode::published()->findOrFail($id);

        $existing = PodcastSave::where('user_id',$userId)->where('episode_id',$id)->first();

        if ($existing) {
            $existing->delete();
            $saved = false;
        } else {
            PodcastSave::create(['user_id'=>$userId,'episode_id'=>$id]);
            $saved = true;
        }

        return response()->json(['status'=>'success','saved'=>$saved]);
    }

    // GET /api/v1/podcast/saved
    public function saved(Request $request)
    {
        $userId = auth()->id();

        $episodes = PodcastEpisode::published()
            ->whereHas('saves', fn($q) => $q->where('user_id',$userId))
            ->with('podcast:id,title,slug,cover_image')
            ->select('id','podcast_id','title','slug','cover_image','duration','play_count','published_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['status'=>'success','data'=>$episodes]);
    }

    private function mediaUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http')) return $path;
        return url('/api/v1/media?f=' . ltrim($path, '/'));
    }
}
