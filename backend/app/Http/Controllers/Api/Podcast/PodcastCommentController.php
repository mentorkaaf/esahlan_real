<?php
namespace App\Http\Controllers\Api\Podcast;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PodcastCommentController extends Controller
{
    // GET /api/v1/podcast/episodes/{id}/comments
    public function index(Request $request, int $episodeId)
    {
        $userId = auth()->id();

        $comments = DB::table('podcast_comments as c')
            ->join('users as u', 'u.id', 'c.user_id')
            ->where('c.episode_id', $episodeId)
            ->whereNull('c.parent_id')
            ->whereNull('c.deleted_at')
            ->select(
                'c.id','c.body','c.likes','c.is_pinned','c.created_at','c.parent_id',
                'u.id as user_id','u.name as user_name','u.profile_photo_path as user_avatar'
            )
            ->orderByDesc('c.is_pinned')
            ->orderByDesc('c.created_at')
            ->paginate(20);

        // Attach reply count + liked state
        $likedIds = $userId
            ? DB::table('podcast_comment_likes')->where('user_id', $userId)
                ->whereIn('comment_id', collect($comments->items())->pluck('id'))
                ->pluck('comment_id')->all()
            : [];

        $replyCounts = DB::table('podcast_comments')
            ->whereIn('parent_id', collect($comments->items())->pluck('id'))
            ->whereNull('deleted_at')
            ->groupBy('parent_id')
            ->select('parent_id', DB::raw('COUNT(*) as cnt'))
            ->pluck('cnt', 'parent_id');

        $items = collect($comments->items())->map(fn($c) => array_merge((array)$c, [
            'is_liked'    => in_array($c->id, $likedIds),
            'reply_count' => $replyCounts[$c->id] ?? 0,
            'user_avatar' => $c->user_avatar ? url('/api/v1/media?f=' . ltrim($c->user_avatar, '/')) : null,
        ]));

        return response()->json([
            'status' => 'success',
            'data'   => $items,
            'meta'   => [
                'current_page' => $comments->currentPage(),
                'last_page'    => $comments->lastPage(),
                'total'        => $comments->total(),
            ],
        ]);
    }

    // GET /api/v1/podcast/comments/{id}/replies
    public function replies(Request $request, int $commentId)
    {
        $userId = auth()->id();

        $replies = DB::table('podcast_comments as c')
            ->join('users as u', 'u.id', 'c.user_id')
            ->where('c.parent_id', $commentId)
            ->whereNull('c.deleted_at')
            ->select('c.id','c.body','c.likes','c.created_at','u.id as user_id','u.name as user_name','u.profile_photo_path as user_avatar')
            ->orderBy('c.created_at')
            ->get()
            ->map(fn($c) => array_merge((array)$c, [
                'user_avatar' => $c->user_avatar ? url('/api/v1/media?f=' . ltrim($c->user_avatar, '/')) : null,
            ]));

        return response()->json(['status' => 'success', 'data' => $replies]);
    }

    // POST /api/v1/podcast/episodes/{id}/comments
    public function store(Request $request, int $episodeId)
    {
        $validated = $request->validate([
            'body'      => 'required|string|max:1000',
            'parent_id' => 'nullable|exists:podcast_comments,id',
        ]);

        $id = DB::table('podcast_comments')->insertGetId([
            'episode_id' => $episodeId,
            'user_id'    => auth()->id(),
            'parent_id'  => $validated['parent_id'] ?? null,
            'body'       => $validated['body'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('podcast_episodes')->where('id', $episodeId)->increment('comment_count');

        $comment = DB::table('podcast_comments as c')
            ->join('users as u', 'u.id', 'c.user_id')
            ->where('c.id', $id)
            ->select('c.*','u.name as user_name','u.profile_photo_path as user_avatar')
            ->first();

        return response()->json(['status' => 'success', 'comment' => $comment], 201);
    }

    // POST /api/v1/podcast/comments/{id}/like
    public function like(int $commentId)
    {
        $userId = auth()->id();
        $exists = DB::table('podcast_comment_likes')->where('user_id', $userId)->where('comment_id', $commentId)->exists();

        if ($exists) {
            DB::table('podcast_comment_likes')->where('user_id', $userId)->where('comment_id', $commentId)->delete();
            DB::table('podcast_comments')->where('id', $commentId)->decrement('likes');
            $liked = false;
        } else {
            DB::table('podcast_comment_likes')->insert(['user_id'=>$userId,'comment_id'=>$commentId,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('podcast_comments')->where('id', $commentId)->increment('likes');
            $liked = true;
        }

        return response()->json(['status' => 'success', 'liked' => $liked]);
    }

    // DELETE /api/v1/podcast/comments/{id}
    public function destroy(int $commentId)
    {
        DB::table('podcast_comments')
            ->where('id', $commentId)
            ->where('user_id', auth()->id())
            ->update(['deleted_at' => now()]);

        return response()->json(['status' => 'success']);
    }
}
