<?php
namespace App\Http\Controllers\Api\Podcast;

use App\Http\Controllers\Controller;
use App\Models\PodcastEpisode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PodcastLibraryController extends Controller
{
    // GET /api/v1/podcast/library  — all library data in one call
    public function index(Request $request)
    {
        $userId = auth()->id();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'continue_listening' => $this->continueListening($userId),
                'downloads'          => $this->downloads($userId),
                'saved'              => $this->saved($userId),
                'liked'              => $this->liked($userId),
                'history'            => $this->history($userId),
                'subscriptions'      => $this->subscriptions($userId),
                'playlists'          => $this->playlists($userId),
                'queue'              => $this->queue($userId),
            ],
        ]);
    }

    // GET /api/v1/podcast/library/history
    public function history(int $userId = 0, Request $request = null)
    {
        $uid = $userId ?: auth()->id();
        $data = DB::table('podcast_episode_plays as p')
            ->join('podcast_episodes as e', 'e.id', 'p.episode_id')
            ->join('podcasts as pod', 'pod.id', 'e.podcast_id')
            ->where('p.user_id', $uid)
            ->select(
                'e.id','e.title','e.slug','e.cover_image','e.duration',
                'pod.title as podcast_title','pod.cover_image as podcast_cover',
                'p.position','p.completed','p.last_played_at'
            )
            ->orderByDesc('p.last_played_at')
            ->limit(50)
            ->get()
            ->map(fn($r) => $this->mapEpisode($r));

        if ($request) return response()->json(['status'=>'success','data'=>$data]);
        return $data->toArray();
    }

    // GET /api/v1/podcast/library/queue
    public function queue(int $userId = 0, Request $request = null)
    {
        $uid = $userId ?: auth()->id();
        $data = DB::table('podcast_queue as q')
            ->join('podcast_episodes as e', 'e.id', 'q.episode_id')
            ->join('podcasts as pod', 'pod.id', 'e.podcast_id')
            ->where('q.user_id', $uid)
            ->select('q.position','e.id','e.title','e.slug','e.cover_image','e.duration','pod.title as podcast_title')
            ->orderBy('q.position')
            ->get()
            ->map(fn($r) => $this->mapEpisode($r));

        if ($request) return response()->json(['status'=>'success','data'=>$data]);
        return $data->toArray();
    }

    // POST /api/v1/podcast/library/queue/{episodeId}
    public function addToQueue(int $episodeId)
    {
        $userId = auth()->id();
        $max = DB::table('podcast_queue')->where('user_id', $userId)->max('position') ?? 0;

        DB::table('podcast_queue')->updateOrInsert(
            ['user_id' => $userId, 'episode_id' => $episodeId],
            ['position' => $max + 1, 'updated_at' => now(), 'created_at' => now()]
        );

        return response()->json(['status' => 'success']);
    }

    // DELETE /api/v1/podcast/library/queue/{episodeId}
    public function removeFromQueue(int $episodeId)
    {
        DB::table('podcast_queue')->where('user_id', auth()->id())->where('episode_id', $episodeId)->delete();
        return response()->json(['status' => 'success']);
    }

    // GET /api/v1/podcast/library/playlists
    public function playlists(int $userId = 0, Request $request = null)
    {
        $uid  = $userId ?: auth()->id();
        $data = DB::table('podcast_playlists')
            ->where('user_id', $uid)
            ->orderByDesc('updated_at')
            ->get();

        if ($request) return response()->json(['status'=>'success','data'=>$data]);
        return $data->toArray();
    }

    // POST /api/v1/podcast/library/playlists
    public function createPlaylist(Request $request)
    {
        $validated = $request->validate(['title' => 'required|string|max:100', 'privacy' => 'in:public,private']);
        $id = DB::table('podcast_playlists')->insertGetId([
            'user_id'    => auth()->id(),
            'title'      => $validated['title'],
            'privacy'    => $validated['privacy'] ?? 'private',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['status' => 'success', 'id' => $id], 201);
    }

    // POST /api/v1/podcast/library/playlists/{id}/episodes/{episodeId}
    public function addToPlaylist(int $playlistId, int $episodeId)
    {
        $playlist = DB::table('podcast_playlists')->where('id',$playlistId)->where('user_id',auth()->id())->first();
        if (!$playlist) return response()->json(['status'=>'error'],403);

        $max = DB::table('podcast_playlist_episodes')->where('playlist_id',$playlistId)->max('position') ?? 0;
        DB::table('podcast_playlist_episodes')->updateOrInsert(
            ['playlist_id' => $playlistId, 'episode_id' => $episodeId],
            ['position' => $max+1, 'updated_at' => now(), 'created_at' => now()]
        );
        DB::table('podcast_playlists')->where('id',$playlistId)->update(['episode_count' => DB::raw('episode_count+1'), 'updated_at' => now()]);

        return response()->json(['status'=>'success']);
    }

    private function subscriptions(int $userId): array
    {
        return DB::table('podcast_follows as f')
            ->join('podcasts as p', 'p.id', 'f.podcast_id')
            ->where('f.user_id', $userId)
            ->where('p.status', 'active')
            ->select('p.id','p.title','p.slug','p.cover_image','p.total_episodes','p.is_verified')
            ->orderByDesc('f.created_at')
            ->limit(20)
            ->get()
            ->map(fn($p) => array_merge((array)$p, ['cover_image' => $this->media($p->cover_image)]))
            ->toArray();
    }

    private function continueListening(int $userId): array
    {
        return DB::table('podcast_episode_plays as p')
            ->join('podcast_episodes as e', 'e.id', 'p.episode_id')
            ->join('podcasts as pod', 'pod.id', 'e.podcast_id')
            ->where('p.user_id', $userId)
            ->where('p.completed', false)
            ->where('p.position', '>', 10)
            ->select('e.id','e.title','e.slug','e.cover_image','e.duration','e.audio_url',
                     'pod.id as podcast_id','pod.title as podcast_title','pod.cover_image as podcast_cover',
                     'p.position','p.last_played_at')
            ->orderByDesc('p.last_played_at')
            ->limit(8)
            ->get()
            ->map(fn($r) => array_merge($this->mapEpisode($r), [
                'progress' => $r->duration > 0 ? round($r->position / $r->duration * 100) : 0,
            ]))
            ->toArray();
    }

    private function downloads(int $userId): array
    {
        return DB::table('podcast_downloads as d')
            ->join('podcast_episodes as e', 'e.id', 'd.episode_id')
            ->join('podcasts as pod', 'pod.id', 'e.podcast_id')
            ->where('d.user_id', $userId)
            ->where('d.status', 'completed')
            ->select('e.id','e.title','e.slug','e.cover_image','e.duration','d.file_size','d.status')
            ->orderByDesc('d.created_at')
            ->limit(20)
            ->get()
            ->map(fn($r) => $this->mapEpisode($r))
            ->toArray();
    }

    private function saved(int $userId): array
    {
        return DB::table('podcast_saves as s')
            ->join('podcast_episodes as e', 'e.id', 's.episode_id')
            ->join('podcasts as pod', 'pod.id', 'e.podcast_id')
            ->where('s.user_id', $userId)
            ->select('e.id','e.title','e.slug','e.cover_image','e.duration','pod.title as podcast_title')
            ->orderByDesc('s.created_at')
            ->limit(20)
            ->get()
            ->map(fn($r) => $this->mapEpisode($r))
            ->toArray();
    }

    private function liked(int $userId): array
    {
        return DB::table('podcast_likes as l')
            ->join('podcast_episodes as e', 'e.id', 'l.episode_id')
            ->join('podcasts as pod', 'pod.id', 'e.podcast_id')
            ->where('l.user_id', $userId)
            ->select('e.id','e.title','e.slug','e.cover_image','e.duration','pod.title as podcast_title')
            ->orderByDesc('l.created_at')
            ->limit(20)
            ->get()
            ->map(fn($r) => $this->mapEpisode($r))
            ->toArray();
    }

    private function mapEpisode(object $r): array
    {
        $arr = (array)$r;
        if (isset($arr['cover_image'])) {
            $arr['cover_image'] = $this->media($arr['cover_image']);
        }
        if (isset($arr['podcast_cover'])) {
            $arr['podcast_cover'] = $this->media($arr['podcast_cover']);
        }
        if (isset($arr['audio_url'])) {
            $arr['audio_url'] = $this->media($arr['audio_url']);
        }
        return $arr;
    }

    private function media(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http')) return $path;
        return url('/api/v1/media?f=' . ltrim($path, '/'));
    }
}
