<?php

namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use App\Models\CommunityHighlight;
use Illuminate\Http\Request;
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
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get()
            ->map(fn($h) => $this->format($h));

        return response()->json(['data' => $highlights]);
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
