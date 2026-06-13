<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Module;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    /**
     * GET /api/banners?position=home_top
     * Public endpoint — no auth required.
     * Returns banners filtered by position.
     * Positions: home_top, home_middle, module_top, popup
     */
    public function banners(Request $request)
    {
        $position = $request->input('position');

        // Allowed positions that are considered "home" (default when no param given)
        $homePositions = ['home_top', 'home_middle'];

        $cacheKey = $position
            ? "banners.public2.pos.{$position}"
            : 'banners.public2.home';

        $banners = Cache::remember($cacheKey, 120, function () use ($position, $homePositions) {
            $query = DB::table('banners')->where('is_active', true);

            if ($position) {
                $query->where('position', $position);
            } else {
                $query->whereIn('position', $homePositions);
            }

            return $query->orderBy('sort_order')->get()->map(function ($b) {
                // Use /api/img/ proxy — has CORS headers for Flutter Web.
                // If the stored path is already a full URL, use it directly.
                $imgUrl = null;
                if ($b->image) {
                    $imgUrl = str_starts_with($b->image, 'http')
                        ? $b->image
                        : url('/api/img/' . $b->image);
                }
                return [
                    'id'          => $b->id,
                    'title'       => $b->title ?? '',
                    'image'       => $imgUrl,
                    'image_url'   => $imgUrl,
                    'action_type' => $b->link_type  ?? 'none',
                    'action_url'  => $b->link_value ?? null,
                    'position'    => $b->position,
                    'sort_order'  => $b->sort_order,
                ];
            });
        });

        return response()->json(['success' => true, 'data' => $banners]);
    }

    public function modules()
    {
        $modules = Cache::remember('modules.active', 300, function () {
            return Module::where('is_active', true)->orderBy('sort_order')->get();
        });

        return response()->json(['success' => true, 'data' => $modules]);
    }

    public function moduleDetails($slug)
    {
        $module = Module::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => $module,
        ]);
    }

    public function districts()
    {
        $districts = Cache::remember('districts.active', 600, function () {
            return DB::table('districts')->where('status', 'active')->orderBy('name')->get();
        });

        return response()->json(['success' => true, 'data' => $districts]);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        // Banners — home positions only, mapped to Flutter format
        $banners = Cache::remember('banners.home.v4', 120, function () {
            return DB::table('banners')
                ->where('is_active', true)
                ->whereIn('position', ['home_top', 'home_middle'])
                ->orderBy('sort_order')
                ->get()
                ->map(function ($b) {
                    $imgUrl = null;
                    if ($b->image) {
                        $imgUrl = str_starts_with($b->image, 'http')
                            ? $b->image
                            : url('/api/img/' . $b->image);
                    }
                    return [
                        'id'          => $b->id,
                        'title'       => $b->title ?? '',
                        'subtitle'    => null,
                        'image'       => $imgUrl,
                        'image_url'   => $imgUrl,
                        'action_type' => $b->link_type  ?? 'none',
                        'action_url'  => $b->link_value ?? null,
                        'link_type'   => $b->link_type  ?? 'none',
                        'link_value'  => $b->link_value ?? null,
                        'sort_order'  => $b->sort_order,
                    ];
                });
        });

        // Modules
        $modules = Cache::remember('modules.active', 300, function () {
            return Module::where('is_active', true)->orderBy('sort_order')->get();
        });

        // Featured vendors
        $featuredVendors = Vendor::where('is_featured', true)
            ->where('is_active', true)
            ->where('is_approved', true)
            ->with('module')
            ->limit(10)
            ->get();

        // Popular products
        $popularProducts = Product::where('is_available', true)
            ->orderBy('total_orders', 'desc')
            ->with(['vendor', 'images'])
            ->limit(12)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'banners'          => $banners,
                'modules'          => $modules,
                'featured_vendors' => $featuredVendors,
                'popular_products' => $popularProducts,
                'user'             => [
                    'name'           => $user->name,
                    'wallet_balance' => $user->wallet?->balance ?? 0,
                    'loyalty_points' => $user->loyalty_points ?? 0,
                ],
            ],
        ]);
    }

    public function search(Request $request)
    {
        $q      = $request->input('q', '');
        $module = $request->input('module');
        $district = $request->input('district_id');

        if (strlen($q) < 2) {
            return response()->json(['success' => false, 'message' => 'Search term too short'], 422);
        }

        // Search vendors
        $vendorsQuery = Vendor::where('is_active', true)->where('is_approved', true)
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%$q%")
                    ->orWhere('description', 'like', "%$q%");
            });

        if ($module) $vendorsQuery->where('module_slug', $module);
        if ($district) $vendorsQuery->where('district_id', $district);

        $vendors = $vendorsQuery->with('module')->limit(10)->get();

        // Search products
        $products = Product::where('is_available', true)
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%$q%")
                    ->orWhere('description', 'like', "%$q%");
            })
            ->with(['vendor', 'images'])
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'vendors'  => $vendors,
                'products' => $products,
                'query'    => $q,
            ],
        ]);
    }
}
