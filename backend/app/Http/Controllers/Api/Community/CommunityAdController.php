<?php
namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use App\Models\CommunityAd;
use App\Models\CommunityAdInteraction;
use App\Models\CommunityAdPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommunityAdController extends Controller
{
    // Get ad pricing options (for ad creation UI)
    public function pricing()
    {
        $pricing = CommunityAdPricing::where('is_active', true)->get();
        return response()->json(['status' => 'success', 'data' => $pricing]);
    }

    // Create a new ad
    public function store(Request $request)
    {
        $data = $request->validate([
            'page_id'        => 'required|exists:community_business_pages,id',
            'title'          => 'required|string|max:100',
            'description'    => 'nullable|string|max:500',
            'ad_type'        => 'required|in:image,video',
            'media'          => 'required|file|max:51200',
            'thumbnail'      => 'nullable|image|max:5120',
            'cta_text'       => 'nullable|string|max:30',
            'cta_url'        => 'nullable|string|max:500',
            'placement'      => 'required|in:feed,reels,explore,stories',
            'budget'         => 'required|numeric|min:1',
            'payment_method' => 'required|in:wallet,waafi_pay',
            'starts_at'      => 'nullable|date',
            'ends_at'        => 'nullable|date|after:starts_at',
            'target_district'=> 'nullable|string',
            'target_gender'  => 'nullable|in:all,male,female',
        ]);

        // Find matching pricing
        $pricing = CommunityAdPricing::where('ad_type', $data['ad_type'])
            ->where('placement', $data['placement'])
            ->where('is_active', true)
            ->first();

        if ($pricing && $data['budget'] < $pricing->min_budget) {
            return response()->json(['status' => 'error', 'message' => "Minimum budget is \${$pricing->min_budget}"], 422);
        }

        // Check wallet balance
        $user = $request->user();
        if ($data['payment_method'] === 'wallet') {
            if (!$user->wallet || $user->wallet->balance < $data['budget']) {
                return response()->json(['status' => 'error', 'message' => 'Insufficient wallet balance'], 422);
            }
        }

        // Upload media
        $path = $request->file('media')->store('community-ads', 'public');
        $mediaUrl = url('/api/v1/img/' . $path);

        // Thumbnail: use separate upload if provided, or page avatar as fallback
        $thumbnailUrl = null;
        if ($request->hasFile('thumbnail')) {
            $thumbPath = $request->file('thumbnail')->store('community-ads/thumbs', 'public');
            $thumbnailUrl = url('/api/v1/img/' . $thumbPath);
        } elseif ($data['ad_type'] === 'video') {
            $page = \App\Models\CommunityBusinessPage::find($data['page_id']);
            $thumbnailUrl = $page?->avatar ? cdn_url($page->avatar) : null;
        }

        $ad = CommunityAd::create([
            'user_id'          => auth()->id(),
            'page_id'          => $data['page_id'],
            'pricing_id'       => $pricing?->id,
            'title'            => $data['title'],
            'description'      => $data['description'] ?? null,
            'ad_type'          => $data['ad_type'],
            'media_url'        => $mediaUrl,
            'thumbnail_url'    => $thumbnailUrl,
            'cta_text'         => $data['cta_text'] ?? 'Learn More',
            'cta_url'          => $data['cta_url'] ?? null,
            'placement'        => $data['placement'],
            'budget'           => $data['budget'],
            'status'           => 'pending',
            'payment_method'   => $data['payment_method'],
            'starts_at'        => $data['starts_at'] ?? now(),
            'ends_at'          => $data['ends_at'] ?? null,
            'target_district'  => $data['target_district'] ?? null,
            'target_gender'    => $data['target_gender'] ?? 'all',
        ]);

        // Deduct wallet
        if ($data['payment_method'] === 'wallet') {
            $user->wallet->decrement('balance', $data['budget']);
        }

        return response()->json(['status' => 'success', 'data' => $ad, 'message' => 'Ad submitted for review'], 201);
    }

    // My ads
    public function myAds()
    {
        $ads = CommunityAd::where('user_id', auth()->id())
            ->with('page:id,name,avatar')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($a) => $this->transform($a));

        return response()->json(['status' => 'success', 'data' => $ads]);
    }

    // Get ads for feed (called by feed endpoint to inject ads)
    public static function getAdsForPlacement(string $placement, int $userId, int $limit = 2): array
    {
        $ads = CommunityAd::with('page:id,name,avatar')
            ->where('status', 'active')
            ->where('placement', $placement)
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->whereColumn('spent', '<', 'budget')
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        $result = [];
        foreach ($ads as $ad) {
            // Record impression
            CommunityAdInteraction::create([
                'ad_id'   => $ad->id,
                'user_id' => $userId,
                'type'    => 'impression',
            ]);
            $ad->increment('impressions');

            // Charge for impression
            if ($ad->pricing) {
                $cost = $ad->pricing->cost_per_impression;
                $ad->increment('spent', $cost);
            }

            $result[] = [
                'id'            => $ad->id,
                'is_ad'         => true,
                'title'         => $ad->title,
                'description'   => $ad->description,
                'ad_type'       => $ad->ad_type,
                'media_url'     => cdn_url($ad->media_url),
                'thumbnail_url' => cdn_url($ad->thumbnail_url),
                'cta_text'      => $ad->cta_text,
                'cta_url'       => $ad->cta_url,
                'page'          => $ad->page ? [
                    'id'     => $ad->page->id,
                    'name'   => $ad->page->name,
                    'avatar' => cdn_url($ad->page->avatar),
                ] : null,
            ];
        }

        return $result;
    }

    // Get a pre-roll video ad for fullscreen player
    public function preroll()
    {
        $userId = auth()->id();
        $ad = CommunityAd::with('page:id,name,avatar')
            ->where('status', 'active')
            ->where('ad_type', 'video')
            ->where(function ($q) { $q->whereNull('ends_at')->orWhere('ends_at', '>', now()); })
            ->whereColumn('spent', '<', 'budget')
            ->inRandomOrder()
            ->first();

        if (!$ad) return response()->json(['status' => 'success', 'data' => null]);

        CommunityAdInteraction::create(['ad_id' => $ad->id, 'user_id' => $userId, 'type' => 'impression']);
        $ad->increment('impressions');
        if ($ad->pricing) $ad->increment('spent', $ad->pricing->cost_per_impression);

        return response()->json(['status' => 'success', 'data' => [
            'id'          => $ad->id,
            'title'       => $ad->title,
            'media_url'   => cdn_url($ad->media_url),
            'cta_text'    => $ad->cta_text,
            'cta_url'     => $ad->cta_url,
            'page'        => $ad->page ? ['name' => $ad->page->name, 'avatar' => cdn_url($ad->page->avatar)] : null,
        ]]);
    }

    // Track ad click
    public function trackClick($id)
    {
        $ad = CommunityAd::findOrFail($id);
        CommunityAdInteraction::create([
            'ad_id'   => $ad->id,
            'user_id' => auth()->id(),
            'type'    => 'click',
        ]);
        $ad->increment('clicks');

        if ($ad->pricing) {
            $ad->increment('spent', $ad->pricing->cost_per_click);
        }

        // Check if budget exhausted
        if ($ad->spent >= $ad->budget) {
            $ad->update(['status' => 'completed']);
        }

        return response()->json(['status' => 'success']);
    }

    private function transform($ad): array
    {
        return [
            'id'          => $ad->id,
            'title'       => $ad->title,
            'description' => $ad->description,
            'ad_type'     => $ad->ad_type,
            'media_url'   => cdn_url($ad->media_url),
            'placement'   => $ad->placement,
            'budget'      => $ad->budget,
            'spent'       => $ad->spent,
            'impressions' => $ad->impressions,
            'clicks'      => $ad->clicks,
            'status'      => $ad->status,
            'cta_text'    => $ad->cta_text,
            'cta_url'     => $ad->cta_url,
            'page'        => $ad->page ? ['id' => $ad->page->id, 'name' => $ad->page->name, 'avatar' => cdn_url($ad->page->avatar)] : null,
            'starts_at'   => $ad->starts_at,
            'ends_at'     => $ad->ends_at,
            'created_at'  => $ad->created_at,
        ];
    }
}
