<?php
namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdController extends Controller
{
    /**
     * GET /v1/ads
     *
     * Query params:
     *   type   = popup | banner | card          (optional — returns all types if omitted)
     *   module = efood | eshop | null           (optional — targets that module + global ads)
     */
    public function index(Request $request): JsonResponse
    {
        $type   = $request->query('type');
        $module = $request->query('module');
        $cacheKey = 'ads.' . ($type ?? 'all') . '.' . ($module ?? '');

        $ads = Cache::remember($cacheKey, 300, function () use ($type, $module) {
            $query = Ad::active()->orderBy('sort_order')->orderBy('id');

            if ($type === 'popup') {
                $query->whereIn('ad_type', ['popup_fullscreen', 'popup_modal']);
            } elseif ($type === 'banner') {
                $query->whereIn('ad_type', ['banner_slider', 'banner_inline']);
            } elseif ($type === 'card') {
                $query->where('ad_type', 'card');
            }

            if ($module) {
                $query->where(function ($q) use ($module) {
                    $q->whereNull('target_module')
                      ->orWhere('target_module', '')
                      ->orWhere('target_module', 'all')
                      ->orWhere('target_module', $module);
                });
            }

            return $query->get()->map(fn($ad) => $ad->toApiArray())->values();
        });

        return response()->json(['success' => true, 'data' => $ads]);
    }

    /**
     * POST /v1/ads/{id}/track
     *
     * Body: { "action": "impression" | "click" }
     */
    public function track(Request $request, int $id): JsonResponse
    {
        $action = $request->input('action', 'impression');

        $ad = Ad::find($id);
        if (! $ad) {
            return response()->json(['success' => false, 'message' => 'Ad not found'], 404);
        }

        if ($action === 'click') {
            $ad->increment('clicks');
        } else {
            $ad->increment('impressions');
        }

        return response()->json(['success' => true]);
    }
}
