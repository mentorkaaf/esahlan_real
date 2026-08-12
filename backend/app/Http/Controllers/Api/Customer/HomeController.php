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
                $imgUrl = cdn_url($b->image);
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
                    $imgUrl = cdn_url($b->image);
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

    // ──────────────────────────────────────────────────────────────────────────
    // GET /home/live-offers
    // Active eFood discount campaigns + eShop sale products — only when live.
    // ──────────────────────────────────────────────────────────────────────────
    public function liveOffers(Request $request)
    {
        $now = now();

        // ── eFood: active discount campaigns ──────────────────────────────────
        $efoodCampaigns = DB::table('discount_campaigns as dc')
            ->join('vendors as v', 'dc.vendor_id', '=', 'v.id')
            ->where('dc.is_active', true)
            ->where('dc.starts_at', '<=', $now)
            ->where('dc.ends_at',   '>=', $now)
            ->where('v.is_active', true)
            ->where('v.is_approved', true)
            ->whereNull('v.deleted_at')
            ->select([
                'dc.id', 'dc.discount_type', 'dc.discount_value', 'dc.ends_at', 'dc.badge_color',
                'v.id as vendor_id', 'v.name as vendor_name', 'v.logo', 'v.cover_image',
            ])
            ->orderBy('dc.ends_at')
            ->limit(8)
            ->get()
            ->map(function ($c) {
                $label = $c->discount_type === 'percentage'
                    ? '-' . (int)$c->discount_value . '%'
                    : '-$' . number_format($c->discount_value, 0);
                return [
                    'id'          => 'camp_' . $c->id,
                    'type'        => 'efood',
                    'title'       => $c->vendor_name,
                    'subtitle'    => 'eFood',
                    'badge'       => $label,
                    'badge_color' => $c->badge_color ?? 'red',
                    'image'       => cdn_url($c->logo ?? $c->cover_image),
                    'ends_at'     => $c->ends_at,
                    'deep_link'   => '/efood/restaurant/' . $c->vendor_id,
                    'minutes_left'=> (int) now()->diffInMinutes($c->ends_at, false),
                ];
            });

        // ── eShop: products with sale_price < price (active discounts) ────────
        $eshopSales = DB::table('products as p')
            ->join('vendors as v', 'p.vendor_id', '=', 'v.id')
            ->join('modules as m', 'v.module_id', '=', 'm.id')
            ->where('m.slug', 'eshop')
            ->where('p.is_available', true)
            ->whereNull('p.deleted_at')
            ->whereNotNull('p.sale_price')
            ->whereRaw('p.sale_price < p.price')
            ->where('p.sale_price', '>', 0)
            ->where('v.is_active', true)
            ->where('v.is_approved', true)
            ->whereNull('v.deleted_at')
            ->select([
                'p.id', 'p.name', 'p.price', 'p.sale_price', 'p.thumbnail',
                'v.id as vendor_id', 'v.name as vendor_name',
            ])
            ->orderByRaw('(p.price - p.sale_price) / p.price DESC')
            ->limit(8)
            ->get()
            ->map(function ($p) {
                $pct = $p->price > 0 ? (int)(($p->price - $p->sale_price) / $p->price * 100) : 0;
                return [
                    'id'          => 'prod_' . $p->id,
                    'type'        => 'eshop',
                    'title'       => $p->name,
                    'subtitle'    => $p->vendor_name,
                    'badge'       => '-' . $pct . '%',
                    'badge_color' => 'orange',
                    'image'       => cdn_url($p->thumbnail),
                    'price'       => $p->price,
                    'sale_price'  => $p->sale_price,
                    'ends_at'     => null,
                    'deep_link'   => '/eshop/products/' . $p->id,
                    'minutes_left'=> null,
                ];
            });

        $offers = $efoodCampaigns->concat($eshopSales)->values();

        return response()->json([
            'success' => true,
            'data'    => $offers,
            'has_offers' => $offers->isNotEmpty(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GET /home/near-you?lat=&lng=&district_id=
    // Nearby eFood restaurants + eRent listings + eShop stores.
    // ──────────────────────────────────────────────────────────────────────────
    public function nearYou(Request $request)
    {
        $hasGps     = $request->filled('lat') && $request->filled('lng');
        $lat        = $hasGps ? (float) $request->lat : null;
        $lng        = $hasGps ? (float) $request->lng : null;
        $districtId = $request->input('district_id');
        $radius     = (float) $request->get('radius', 10); // km

        $results = [];

        // ── eFood restaurants ─────────────────────────────────────────────────
        $efoodModule = DB::table('modules')->where('slug', 'efood')->first();
        if ($efoodModule) {
            $q = DB::table('vendors')
                ->where('module_id', $efoodModule->id)
                ->where('is_active', true)
                ->where('is_approved', true)
                ->whereNull('deleted_at')
                ->select(['id', 'name', 'logo', 'vendor_type', 'delivery_time', 'rating', 'district_id', 'latitude', 'longitude']);

            if ($hasGps) {
                $q->selectRaw(
                    '(6371 * acos(GREATEST(-1,LEAST(1,
                        cos(radians(?)) * cos(radians(latitude))
                        * cos(radians(longitude) - radians(?))
                        + sin(radians(?)) * sin(radians(latitude))
                    )))) AS distance_km',
                    [$lat, $lng, $lat]
                )
                ->whereNotNull('latitude')->whereNotNull('longitude')
                ->having('distance_km', '<=', $radius)
                ->orderBy('distance_km');
            } elseif ($districtId) {
                $q->where('district_id', $districtId)->orderByDesc('rating');
            } else {
                $q->orderByDesc('rating');
            }

            $q->limit(5)->get()->each(function ($r) use (&$results) {
                $results[] = [
                    'id'          => 'rest_' . $r->id,
                    'type'        => 'efood',
                    'module'      => 'eFood',
                    'title'       => $r->name,
                    'subtitle'    => $r->vendor_type ?? 'Restaurant',
                    'image'       => cdn_url($r->logo),
                    'rating'      => $r->rating,
                    'delivery_time' => $r->delivery_time,
                    'distance_km' => isset($r->distance_km) ? round($r->distance_km, 1) : null,
                    'deep_link'   => '/efood/restaurant/' . $r->id,
                ];
            });
        }

        // ── eRent properties (district-based) ────────────────────────────────
        $erentModule = DB::table('modules')->where('slug', 'erent')->first();
        if ($erentModule && $districtId) {
            DB::table('properties')
                ->where('district_id', $districtId)
                ->where('is_available', true)
                ->whereNull('deleted_at')
                ->select(['id', 'title', 'price', 'thumbnail', 'property_type', 'bedrooms', 'district_id'])
                ->orderByDesc('id')
                ->limit(3)
                ->get()
                ->each(function ($p) use (&$results) {
                    $results[] = [
                        'id'       => 'rent_' . $p->id,
                        'type'     => 'erent',
                        'module'   => 'eRent',
                        'title'    => $p->title,
                        'subtitle' => ($p->property_type ?? 'House') . ($p->bedrooms ? ' · ' . $p->bedrooms . ' beds' : ''),
                        'image'    => cdn_url($p->thumbnail),
                        'price'    => $p->price,
                        'deep_link'=> '/erent',
                    ];
                });
        }

        // ── eShop stores near district ────────────────────────────────────────
        $eshopModule = DB::table('modules')->where('slug', 'eshop')->first();
        if ($eshopModule && ($hasGps || $districtId)) {
            $q2 = DB::table('vendors')
                ->where('module_id', $eshopModule->id)
                ->where('is_active', true)
                ->where('is_approved', true)
                ->whereNull('deleted_at')
                ->select(['id', 'name', 'logo', 'vendor_type', 'rating', 'district_id', 'latitude', 'longitude']);

            if ($hasGps) {
                $q2->selectRaw(
                    '(6371 * acos(GREATEST(-1,LEAST(1,
                        cos(radians(?)) * cos(radians(latitude))
                        * cos(radians(longitude) - radians(?))
                        + sin(radians(?)) * sin(radians(latitude))
                    )))) AS distance_km',
                    [$lat, $lng, $lat]
                )
                ->whereNotNull('latitude')->whereNotNull('longitude')
                ->having('distance_km', '<=', $radius)
                ->orderBy('distance_km');
            } elseif ($districtId) {
                $q2->where('district_id', $districtId)->orderByDesc('rating');
            }

            $q2->limit(3)->get()->each(function ($s) use (&$results) {
                $results[] = [
                    'id'          => 'shop_' . $s->id,
                    'type'        => 'eshop',
                    'module'      => 'eShop',
                    'title'       => $s->name,
                    'subtitle'    => $s->vendor_type ?? 'Store',
                    'image'       => cdn_url($s->logo),
                    'rating'      => $s->rating,
                    'distance_km' => isset($s->distance_km) ? round($s->distance_km, 1) : null,
                    'deep_link'   => '/eshop',
                ];
            });
        }

        return response()->json(['success' => true, 'data' => $results]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GET /home/best-sellers?limit=10
    // Top products sorted by total_orders, cross-module.
    // ──────────────────────────────────────────────────────────────────────────
    public function bestSellers(Request $request)
    {
        $limit = min((int) $request->get('limit', 10), 30);

        // Count orders per product from the orders_items table (or orders table)
        // Falls back gracefully if the column doesn't exist
        $orderCountSub = DB::table('order_items')
            ->selectRaw('product_id, COUNT(*) as order_count')
            ->groupBy('product_id');

        // Try ordered products first; fall back to top-rated if no orders exist yet
        $mapProduct = function ($p, int $orderCount = 0) {
            $deepLink = match($p->module_slug) {
                'efood'   => '/efood/restaurant/' . $p->vendor_id,
                'eshop'   => '/eshop/products/' . $p->id,
                'egrocery'=> '/egrocery',
                default   => '/' . $p->module_slug,
            };
            return [
                'id'           => $p->id,
                'name'         => $p->name,
                'price'        => $p->price,
                'sale_price'   => $p->sale_price,
                'thumbnail'    => cdn_url($p->thumbnail),
                'total_orders' => $orderCount,
                'rating'       => $p->rating,
                'vendor_name'  => $p->vendor_name,
                'vendor_id'    => $p->vendor_id,
                'module_slug'  => $p->module_slug,
                'module_name'  => $p->module_name,
                'deep_link'    => $deepLink,
            ];
        };

        $products = DB::table('products as p')
            ->joinSub($orderCountSub, 'oi', 'p.id', '=', 'oi.product_id')
            ->join('vendors as v', 'p.vendor_id', '=', 'v.id')
            ->join('modules as m', 'v.module_id', '=', 'm.id')
            ->where('p.is_available', true)
            ->whereNull('p.deleted_at')
            ->where('oi.order_count', '>', 0)
            ->where('v.is_active', true)
            ->where('v.is_approved', true)
            ->whereNull('v.deleted_at')
            ->select([
                'p.id', 'p.name', 'p.price', 'p.sale_price', 'p.thumbnail', 'p.rating',
                'oi.order_count as total_orders',
                'v.id as vendor_id', 'v.name as vendor_name',
                'm.slug as module_slug', 'm.name as module_name',
            ])
            ->orderByDesc('oi.order_count')
            ->limit($limit)
            ->get()
            ->map(fn($p) => $mapProduct($p, $p->total_orders));

        // Fallback: if no orders yet, show top-rated available products
        if ($products->isEmpty()) {
            $products = DB::table('products as p')
                ->join('vendors as v', 'p.vendor_id', '=', 'v.id')
                ->join('modules as m', 'v.module_id', '=', 'm.id')
                ->where('p.is_available', true)
                ->whereNull('p.deleted_at')
                ->where('v.is_active', true)
                ->where('v.is_approved', true)
                ->whereNull('v.deleted_at')
                ->where('p.price', '>', 0)
                ->select([
                    'p.id', 'p.name', 'p.price', 'p.sale_price', 'p.thumbnail', 'p.rating',
                    'v.id as vendor_id', 'v.name as vendor_name',
                    'm.slug as module_slug', 'm.name as module_name',
                ])
                ->orderByDesc('p.rating')
                ->orderByDesc('p.id')
                ->limit($limit)
                ->get()
                ->map(fn($p) => $mapProduct($p, 0));
        }

        return response()->json(['success' => true, 'data' => $products]);
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
