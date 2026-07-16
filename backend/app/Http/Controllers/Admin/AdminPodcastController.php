<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Podcast;
use App\Models\PodcastEpisode;
use App\Models\PodcastCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPodcastController extends Controller
{
    public function index()
    {
        $stats = [
            'podcasts'   => Podcast::count(),
            'episodes'   => PodcastEpisode::count(),
            'plays'      => PodcastEpisode::sum('play_count'),
            'live_rooms' => DB::table('podcast_live_rooms')->where('status','live')->count(),
        ];

        $podcasts = Podcast::with(['user:id,name','category:id,name'])
            ->orderByDesc('id')
            ->paginate(20);

        $episodes = PodcastEpisode::with('podcast:id,title')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('admin.podcast.index', compact('stats','podcasts','episodes'));
    }

    public function show($id)
    {
        $podcast  = Podcast::with(['user','category','episodes'])->findOrFail($id);
        $episodes = $podcast->episodes()->orderByDesc('id')->paginate(20);
        return view('admin.podcast.show', compact('podcast','episodes'));
    }

    public function toggleVerify($id)
    {
        $podcast = Podcast::findOrFail($id);
        $podcast->update(['is_verified' => !$podcast->is_verified]);
        return response()->json(['success' => true, 'is_verified' => $podcast->is_verified]);
    }

    public function destroy($id)
    {
        Podcast::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function destroyEpisode($id)
    {
        PodcastEpisode::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function categories()
    {
        $categories = PodcastCategory::withCount(['podcasts'])->orderBy('sort_order')->get();
        return view('admin.podcast.categories', compact('categories'));
    }

    public function updateCategory(Request $request, $id)
    {
        $cat = PodcastCategory::findOrFail($id);

        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'icon'  => 'nullable|string|max:100',
            'color' => 'nullable|string|max:20',
        ]);

        $data['slug'] = \Illuminate\Support\Str::slug($data['name']);

        if ($request->hasFile('cover_image')) {
            $path = $request->file('cover_image')->store('podcast/categories', 'public');
            $data['cover_image'] = $path;
        }

        $cat->update($data);
        return response()->json(['success' => true, 'category' => $cat]);
    }

    public function toggleCategoryStatus($id)
    {
        $cat = PodcastCategory::findOrFail($id);
        $cat->update(['is_active' => !$cat->is_active]);
        return response()->json(['success' => true, 'is_active' => $cat->is_active]);
    }

    public function destroyCategory($id)
    {
        $cat = PodcastCategory::findOrFail($id);
        if ($cat->podcasts()->count() > 0) {
            return response()->json(['success' => false, 'message' => 'Cannot delete: category has podcasts'], 422);
        }
        $cat->delete();
        return response()->json(['success' => true]);
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'icon'  => 'nullable|string|max:100',
            'color' => 'nullable|string|max:20',
        ]);
        $data['slug']       = \Illuminate\Support\Str::slug($data['name']);
        $data['sort_order'] = PodcastCategory::max('sort_order') + 1;
        $data['is_active']  = true;

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('podcast/categories', 'public');
        }

        $cat = PodcastCategory::create($data);
        return response()->json(['success' => true, 'category' => $cat]);
    }
}
