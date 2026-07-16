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
        $categories = PodcastCategory::orderBy('sort_order')->get();
        return view('admin.podcast.categories', compact('categories'));
    }
}
