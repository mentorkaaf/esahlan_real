<?php

namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use App\Models\ESpaceAd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ESpaceAdController extends Controller
{
    /**
     * Return active eSpace ads for a given placement.
     * Returns up to 5 ads, rotated by priority + least-recently-seen for this user.
     */
    public function index(Request $request)
    {
        $placement = $request->get('placement', 'feed');
        $userId    = auth()->id();

        $cacheKey = "espace_ads:{$placement}";

        $ads = Cache::remember($cacheKey, 300, function () use ($placement) {
            return ESpaceAd::active()
                ->forPlacement($placement)
                ->orderByDesc('priority')
                ->limit(20)
                ->get();
        });

        if ($ads->isEmpty()) {
            return response()->json(['status' => 'success', 'data' => []]);
        }

        // Rotate: de-prioritize ads the user has seen in last 2h
        $recentlySeen = DB::table('espace_ad_impressions')
            ->where('user_id', $userId)
            ->where('action', 'view')
            ->where('created_at', '>', now()->subHours(2))
            ->pluck('espace_ad_id')
            ->toArray();

        $sorted = $ads->sortBy(fn ($ad) => in_array($ad->id, $recentlySeen) ? 1 : 0)
                      ->values()
                      ->take(5);

        return response()->json([
            'status' => 'success',
            'data'   => $sorted->map(fn ($ad) => $this->transform($ad)),
        ]);
    }

    /**
     * Track impression (view) for an ad.
     */
    public function impression(Request $request, ESpaceAd $ad)
    {
        $userId = auth()->id();

        // Throttle: max 1 impression per user per ad per hour
        $key = "espace_imp:{$ad->id}:{$userId}";
        if (!Cache::has($key)) {
            DB::table('espace_ad_impressions')->insert([
                'espace_ad_id' => $ad->id,
                'user_id'      => $userId,
                'action'       => 'view',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            $ad->increment('impressions_count');
            Cache::put($key, 1, 3600);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Track click for an ad.
     */
    public function click(Request $request, ESpaceAd $ad)
    {
        $userId = auth()->id();

        DB::table('espace_ad_impressions')->insert([
            'espace_ad_id' => $ad->id,
            'user_id'      => $userId,
            'action'       => 'click',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        $ad->increment('clicks_count');

        return response()->json([
            'status'    => 'success',
            'deep_link' => $ad->deep_link,
        ]);
    }

    private function transform(ESpaceAd $ad): array
    {
        return [
            'id'          => $ad->id,
            'module'      => $ad->module,
            'module_label'=> $ad->module_label,
            'module_color'=> $ad->module_color,
            'title'       => $ad->title,
            'subtitle'    => $ad->subtitle,
            'description' => $ad->description,
            'image_url'   => $ad->image_url,
            'cta_text'    => $ad->cta_text,
            'deep_link'   => $ad->deep_link,
            'placement'   => $ad->placement,
        ];
    }
}
