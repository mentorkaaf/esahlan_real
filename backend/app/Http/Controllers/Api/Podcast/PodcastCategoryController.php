<?php
namespace App\Http\Controllers\Api\Podcast;

use App\Http\Controllers\Controller;
use App\Models\Podcast;
use App\Models\PodcastCategory;
use Illuminate\Http\Request;

class PodcastCategoryController extends Controller
{
    // GET /api/v1/podcast/categories
    public function index()
    {
        $categories = PodcastCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['status'=>'success','data'=>$categories]);
    }

    // GET /api/v1/podcast/categories/{slug}
    public function show(Request $request, string $slug)
    {
        $category = PodcastCategory::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $podcasts = Podcast::published()
            ->where('category_id', $category->id)
            ->select('id','title','slug','cover_image','total_episodes','total_followers','rating','is_verified')
            ->orderByDesc('total_followers')
            ->paginate(20);

        return response()->json([
            'status'   => 'success',
            'category' => $category,
            'podcasts' => $podcasts,
        ]);
    }
}
