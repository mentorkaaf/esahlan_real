<?php
namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommunityHighlightController extends Controller
{
    public function index(int $userId)
    {
        $highlights = DB::table('community_story_highlights')
            ->where('user_id', $userId)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($h) {
                $items = DB::table('community_story_highlight_items')
                    ->join('community_stories', 'community_story_highlight_items.story_id', '=', 'community_stories.id')
                    ->where('highlight_id', $h->id)
                    ->orderBy('community_story_highlight_items.sort_order')
                    ->select('community_stories.*')
                    ->get();
                return [
                    'id' => $h->id,
                    'title' => $h->title,
                    'cover_url' => cdn_url($h->cover_url),
                    'stories_count' => $items->count(),
                    'stories' => $items->map(fn($s) => [
                        'id' => $s->id,
                        'type' => $s->type,
                        'media_url' => cdn_url($s->media_url),
                        'text_content' => $s->text_content,
                        'bg_color' => $s->bg_color,
                    ])->toArray(),
                ];
            });

        return response()->json(['status' => 'success', 'data' => $highlights]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:50',
            'story_ids' => 'required|array|min:1',
            'story_ids.*' => 'exists:community_stories,id',
        ]);

        $id = DB::table('community_story_highlights')->insertGetId([
            'user_id' => auth()->id(),
            'title' => $request->title,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($request->story_ids as $i => $storyId) {
            DB::table('community_story_highlight_items')->insert([
                'highlight_id' => $id,
                'story_id' => $storyId,
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Use first story media as cover
        $first = DB::table('community_stories')->find($request->story_ids[0]);
        if ($first?->media_url) {
            DB::table('community_story_highlights')->where('id', $id)->update(['cover_url' => $first->media_url]);
        }

        return response()->json(['status' => 'success', 'message' => 'Highlight created'], 201);
    }

    public function addStory(Request $request, int $highlightId)
    {
        $highlight = DB::table('community_story_highlights')->where('id', $highlightId)->where('user_id', auth()->id())->first();
        if (!$highlight) return response()->json(['status' => 'error', 'message' => 'Not found'], 404);

        $request->validate(['story_id' => 'required|exists:community_stories,id']);
        $maxSort = DB::table('community_story_highlight_items')->where('highlight_id', $highlightId)->max('sort_order') ?? 0;

        DB::table('community_story_highlight_items')->insert([
            'highlight_id' => $highlightId,
            'story_id' => $request->story_id,
            'sort_order' => $maxSort + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'success']);
    }

    public function destroy(int $id)
    {
        DB::table('community_story_highlights')->where('id', $id)->where('user_id', auth()->id())->delete();
        return response()->json(['status' => 'success']);
    }
}
