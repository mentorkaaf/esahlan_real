<?php

namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use App\Models\CommunityHighlight;
use App\Models\CommunityHighlightItem;
use App\Models\CommunityPost;
use App\Models\CommunityStory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CommunityHighlightController extends Controller
{
    private function format(CommunityHighlight $h): array
    {
        $cover = null;
        if ($h->cover_image) {
            $cover = str_starts_with($h->cover_image, 'http')
                ? $h->cover_image
                : url('storage/' . ltrim($h->cover_image, 'storage/'));
        }
        return [
            'id'          => $h->id,
            'title'       => $h->title,
            'cover_image' => $cover,
            'sort_order'  => $h->sort_order,
        ];
    }

    public function index(int $userId)
    {
        $highlights = CommunityHighlight::where('user_id', $userId)
            ->withCount('items')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get()
            ->map(fn($h) => array_merge($this->format($h), ['items_count' => $h->items_count]));

        return response()->json(['data' => $highlights]);
    }

    // ── Items ────────────────────────────────────────────────────────────────

    public function getItems(int $highlightId)
    {
        $h = CommunityHighlight::findOrFail($highlightId);
        $viewerId = auth()->id();

        // Respect privacy: only owner or followers of private accounts can view
        if ($h->user_id !== $viewerId) {
            $isPrivate = DB::table('user_settings')
                ->where('user_id', $h->user_id)
                ->value('privacy');
            $settings = $isPrivate ? json_decode($isPrivate, true) : [];
            if (!empty($settings['private_account'])) {
                $follows = DB::table('community_follows')
                    ->where('follower_id', $viewerId)
                    ->where('following_id', $h->user_id)
                    ->where('status', 'accepted')
                    ->exists();
                if (!$follows) {
                    return response()->json(['data' => []]);
                }
            }
        }

        $items = CommunityHighlightItem::where('highlight_id', $highlightId)
            ->orderBy('sort_order')
            ->get();

        $postIds   = $items->where('content_type', 'post')->pluck('content_id')->toArray();
        $storyIds  = $items->where('content_type', 'story')->pluck('content_id')->toArray();

        $posts   = CommunityPost::with(['media', 'user.communityProfile'])
            ->whereIn('id', $postIds)->get()->keyBy('id');
        $stories = CommunityStory::with(['user.communityProfile'])
            ->whereIn('id', $storyIds)->get()->keyBy('id');

        $result = $items->map(function ($item) use ($posts, $stories, $viewerId) {
            if ($item->content_type === 'post') {
                $post = $posts->get($item->content_id);
                if (!$post) return null;
                $media = $post->media->first();
                return [
                    'item_id'      => $item->id,
                    'content_type' => 'post',
                    'content_id'   => $item->content_id,
                    'post_type'    => $post->type,
                    'thumbnail_url'=> $media ? $this->mediaUrl($media->thumbnail_url ?? $media->url) : null,
                    'media_url'    => $media ? $this->mediaUrl($media->url) : null,
                    'hls_url'      => $media ? $this->mediaUrl($media->hls_url) : null,
                    'is_video'     => $media && in_array($media->type, ['video', 'reel']),
                    'caption'      => $post->content,
                    'user'         => ['id' => $post->user_id, 'name' => $post->user->name ?? '', 'username' => $post->user->communityProfile->username ?? ''],
                    'likes_count'  => $post->likes_count ?? 0,
                ];
            } else {
                $story = $stories->get($item->content_id);
                if (!$story) return null;
                return [
                    'item_id'      => $item->id,
                    'content_type' => 'story',
                    'content_id'   => $item->content_id,
                    'post_type'    => 'story',
                    'thumbnail_url'=> $this->mediaUrl($story->thumbnail_url ?? $story->media_url),
                    'media_url'    => $this->mediaUrl($story->media_url),
                    'hls_url'      => null,
                    'is_video'     => $story->media_type === 'video',
                    'caption'      => $story->caption ?? '',
                    'user'         => ['id' => $story->user_id, 'name' => $story->user->name ?? '', 'username' => $story->user->communityProfile->username ?? ''],
                    'likes_count'  => 0,
                ];
            }
        })->filter()->values();

        return response()->json([
            'data'    => $result,
            'highlight' => $this->format($h),
        ]);
    }

    public function addItem(Request $request, int $highlightId)
    {
        $request->validate([
            'content_type' => 'required|in:post,story',
            'content_id'   => 'required|integer',
        ]);

        $h = CommunityHighlight::where('id', $highlightId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $count = CommunityHighlightItem::where('highlight_id', $highlightId)->count();

        // upsert — ignore if already added
        DB::table('community_highlight_items')->insertOrIgnore([
            'highlight_id' => $highlightId,
            'content_type' => $request->content_type,
            'content_id'   => $request->content_id,
            'sort_order'   => $count,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return response()->json(['status' => 'success']);
    }

    public function removeItem(int $highlightId, int $itemId)
    {
        $h = CommunityHighlight::where('id', $highlightId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        CommunityHighlightItem::where('highlight_id', $highlightId)
            ->where('id', $itemId)
            ->delete();

        return response()->json(['status' => 'success']);
    }

    private function mediaUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http')) return $path;
        return url('storage/' . ltrim($path, 'storage/'));
    }

    public function store(Request $request)
    {
        $request->validate(['title' => 'required|string|max:50']);

        $userId = auth()->id();
        $coverPath = null;

        if ($request->hasFile('cover_image')) {
            $path = $request->file('cover_image')->store('highlights', 'public');
            $coverPath = $path;
        }

        $count = CommunityHighlight::where('user_id', $userId)->count();

        $h = CommunityHighlight::create([
            'user_id'     => $userId,
            'title'       => $request->title,
            'cover_image' => $coverPath,
            'sort_order'  => $count,
        ]);

        return response()->json(['data' => $this->format($h)], 201);
    }

    public function update(Request $request, int $id)
    {
        $h = CommunityHighlight::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if ($request->has('title')) {
            $h->title = $request->input('title');
        }

        if ($request->hasFile('cover_image')) {
            if ($h->cover_image) {
                Storage::disk('public')->delete($h->cover_image);
            }
            $h->cover_image = $request->file('cover_image')->store('highlights', 'public');
        }

        $h->save();

        return response()->json(['data' => $this->format($h)]);
    }

    public function destroy(int $id)
    {
        $h = CommunityHighlight::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if ($h->cover_image) {
            Storage::disk('public')->delete($h->cover_image);
        }

        $h->delete();

        return response()->json(['message' => 'deleted']);
    }
}
