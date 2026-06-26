<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityAd;
use App\Models\CommunityAdPricing;
use App\Models\CommunityBusinessPage;
use Illuminate\Http\Request;

class AdminCommunityAdsController extends Controller
{
    public function index()
    {
        $ads = CommunityAd::with(['user', 'page', 'pricing'])->orderByDesc('created_at')->paginate(30);
        $pricing = CommunityAdPricing::all();
        $pages = CommunityBusinessPage::withCount('followers')->orderByDesc('followers_count')->get();

        $stats = [
            'total_ads'       => CommunityAd::count(),
            'active_ads'      => CommunityAd::where('status', 'active')->count(),
            'pending_ads'     => CommunityAd::where('status', 'pending')->count(),
            'total_revenue'   => CommunityAd::sum('spent'),
            'total_clicks'    => CommunityAd::sum('clicks'),
            'total_impressions' => CommunityAd::sum('impressions'),
            'total_pages'     => CommunityBusinessPage::count(),
        ];

        return view('admin.community-ads.index', compact('ads', 'pricing', 'pages', 'stats'));
    }

    public function updateAdStatus(Request $request, $id)
    {
        $ad = CommunityAd::findOrFail($id);
        $request->validate(['status' => 'required|in:active,paused,rejected,completed']);
        $ad->update(['status' => $request->status]);

        // Refund if rejected
        if ($request->status === 'rejected' && $ad->spent < $ad->budget) {
            $refund = $ad->budget - $ad->spent;
            if ($ad->user?->wallet) {
                $ad->user->wallet->increment('balance', $refund);
            }
        }

        return back()->with('success', "Ad #{$ad->id} status updated to {$request->status}");
    }

    public function deleteAd($id)
    {
        $ad = CommunityAd::findOrFail($id);
        // Refund remaining budget
        if ($ad->spent < $ad->budget && $ad->user?->wallet) {
            $ad->user->wallet->increment('balance', $ad->budget - $ad->spent);
        }
        $ad->delete();
        return back()->with('success', 'Ad deleted and remaining budget refunded.');
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) return back()->with('success', 'No ads selected.');
        $ads = CommunityAd::whereIn('id', $ids)->get();
        foreach ($ads as $ad) {
            if ($ad->spent < $ad->budget && $ad->user?->wallet) {
                $ad->user->wallet->increment('balance', $ad->budget - $ad->spent);
            }
            $ad->delete();
        }
        return back()->with('success', count($ids) . ' ads deleted.');
    }

    public function saveSettings(Request $request)
    {
        $settings = [
            'feed_ads_enabled'       => $request->boolean('feed_ads_enabled'),
            'feed_ad_frequency'      => (int) ($request->feed_ad_frequency ?? 5),
            'feed_max_ads'           => (int) ($request->feed_max_ads ?? 3),
            'feed_placements'        => $request->input('feed_placements', ['feed']),
            'overlay_ads_enabled'    => $request->boolean('overlay_ads_enabled'),
            'overlay_skip_seconds'   => (int) ($request->overlay_skip_seconds ?? 10),
            'overlay_max_per_video'  => (int) ($request->overlay_max_per_video ?? 3),
            'overlay_placements'     => $request->input('overlay_placements', ['feed', 'reels']),
            'overlay_min_video_length'=> (int) ($request->overlay_min_video_length ?? 15),
            'freq_short'             => (int) ($request->freq_short ?? 1),
            'freq_medium'            => (int) ($request->freq_medium ?? 2),
            'freq_long'              => (int) ($request->freq_long ?? 3),
            'freq_very_long'         => (int) ($request->freq_very_long ?? 6),
            'video_compress_enabled' => $request->boolean('video_compress_enabled'),
            'video_compress_quality' => $request->video_compress_quality ?? 'default',
            'video_compress_ads'     => $request->boolean('video_compress_ads'),
        ];

        \DB::table('settings')->updateOrInsert(
            ['key' => 'ad_display_settings'],
            ['value' => json_encode($settings), 'updated_at' => now()]
        );

        return back()->with('success', 'Ad display settings saved.');
    }

    public function updatePricing(Request $request, $id)
    {
        $pricing = CommunityAdPricing::findOrFail($id);
        $data = $request->validate([
            'cost_per_click'      => 'required|numeric|min:0',
            'cost_per_impression' => 'required|numeric|min:0',
            'cost_per_1000'       => 'required|numeric|min:0',
            'min_budget'          => 'required|numeric|min:0',
            'is_active'           => 'nullable|boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        $pricing->update($data);
        return back()->with('success', "Pricing '{$pricing->name}' updated");
    }
}
