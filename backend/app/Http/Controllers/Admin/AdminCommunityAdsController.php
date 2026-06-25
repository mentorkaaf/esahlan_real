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
