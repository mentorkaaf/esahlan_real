<?php

namespace App\Http\Controllers\Api\EWholesale;

use App\Http\Controllers\Controller;
use App\Models\EWholesale\{
    EWBanner, EWCategory, EWProduct, EWSupplier, EWDeal, EWRfq
};
use App\Services\EWholesale\CatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EWPublicController extends Controller
{
    public function __construct(private CatalogService $catalog) {}

    // ── GET /api/v1/ewholesale/home ───────────────────────────────────────
    public function home()
    {
        $payload = Cache::remember('ew:home_payload', 120, function () {
            $banners = EWBanner::active()->orderBy('sort_order')->get()
                ->map(fn($b) => [
                    'id'        => $b->id,
                    'title'     => $b->title,
                    'subtitle'  => $b->subtitle,
                    'image_url' => $this->mediaUrl($b->image),
                    'cta_label' => $b->cta_label,
                    'cta_url'   => $b->cta_url,
                    'bg_color'  => $b->bg_color,
                ]);

            $categories = EWCategory::active()->roots()
                ->withCount('products')
                ->orderBy('sort_order')
                ->get()
                ->map(fn($c) => [
                    'id'             => $c->id,
                    'name'           => $c->name,
                    'name_so'        => $c->name_so,
                    'icon'           => $c->icon,
                    'image_url'      => $c->image ? $this->mediaUrl($c->image) : null,
                    'products_count' => $c->products_count,
                ]);

            // Top Deals — active deals with countdown
            $deals = EWDeal::with(['product.supplier:id,display_name,verification'])
                ->where('is_active', true)
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->orderByDesc('deal_price_percent_off')
                ->limit(10)
                ->get()
                ->map(fn($d) => [
                    'deal_id'          => $d->id,
                    'product_id'       => $d->product_id,
                    'product_name'     => $d->product?->name,
                    'first_image'      => $d->product?->firstImage() ? $this->mediaUrl($d->product->firstImage()) : null,
                    'unit'             => $d->product?->unit,
                    'moq'              => $d->product?->moq,
                    'from_price'       => $d->product?->min_price,
                    'percent_off'      => $d->deal_price_percent_off,
                    'min_qty'          => $d->min_qty,
                    'ends_at_iso'      => $d->ends_at->toIso8601String(),
                    'seconds_left'     => max(0, now()->diffInSeconds($d->ends_at, false)),
                    'supplier_name'    => $d->product?->supplier?->display_name,
                    'supplier_verified'=> $d->product?->supplier?->isVerified(),
                ]);

            // Verified Suppliers rail
            $verifiedSuppliers = EWSupplier::verified()->active()
                ->withCount('products')
                ->orderByDesc('rating')
                ->limit(8)
                ->get()
                ->map(fn($s) => [
                    'id'           => $s->id,
                    'display_name' => $s->display_name,
                    'logo_url'     => $s->logo ? $this->mediaUrl($s->logo) : null,
                    'verification' => $s->verification,
                    'rating'       => $s->rating,
                    'total_orders' => $s->total_orders,
                    'products_count' => $s->products_count,
                ]);

            // Best Sellers
            $bestSellers = EWProduct::with('supplier:id,display_name,verification')
                ->where('status', 'active')
                ->orderByDesc('orders_count')
                ->limit(12)
                ->get()
                ->map(fn($p) => $this->productCard($p));

            // New Arrivals
            $newArrivals = EWProduct::with('supplier:id,display_name,verification')
                ->where('status', 'active')
                ->orderByDesc('created_at')
                ->limit(12)
                ->get()
                ->map(fn($p) => $this->productCard($p));

            $openRfqCount = EWRfq::where('status', 'open')->count();

            return compact(
                'banners','categories','deals','verifiedSuppliers',
                'bestSellers','newArrivals','openRfqCount'
            );
        });

        return response()->json(['data' => $payload]);
    }

    // ── GET /api/v1/ewholesale/categories ────────────────────────────────
    public function categories()
    {
        $cats = Cache::remember('ew:cats_api', 300, fn() =>
            EWCategory::active()->roots()
                ->withCount('products')
                ->with('children')
                ->orderBy('sort_order')
                ->get()
                ->map(fn($c) => [
                    'id'             => $c->id,
                    'name'           => $c->name,
                    'name_so'        => $c->name_so,
                    'icon'           => $c->icon,
                    'image_url'      => $c->image ? $this->mediaUrl($c->image) : null,
                    'products_count' => $c->products_count,
                    'children'       => $c->children->map(fn($ch) => [
                        'id'   => $ch->id,
                        'name' => $ch->name,
                        'icon' => $ch->icon,
                    ])->values(),
                ])
        );

        return response()->json(['data' => $cats]);
    }

    // ── GET /api/v1/ewholesale/categories/{id} ────────────────────────────
    public function category(int $id)
    {
        $cat = EWCategory::with('children')->findOrFail($id);
        $descendantIds = $cat->descendantIds();

        // Facets
        $priceRange = EWProduct::whereIn('category_id', $descendantIds)->where('status','active')
            ->selectRaw('MIN(min_price) as min_p, MAX(max_price) as max_p')->first();

        $supplierIds = EWProduct::whereIn('category_id', $descendantIds)->where('status','active')
            ->distinct()->pluck('supplier_id');

        $suppliers = EWSupplier::whereIn('id', $supplierIds)
            ->get(['id','display_name','verification']);

        $moqRanges = [
            ['label'=>'1–10',   'min'=>1,   'max'=>10],
            ['label'=>'11–50',  'min'=>11,  'max'=>50],
            ['label'=>'51–200', 'min'=>51,  'max'=>200],
            ['label'=>'200+',   'min'=>200, 'max'=>null],
        ];

        return response()->json([
            'data' => [
                'category'   => ['id'=>$cat->id,'name'=>$cat->name,'children'=>$cat->children->map(fn($c)=>['id'=>$c->id,'name'=>$c->name])],
                'facets'     => [
                    'price_range' => ['min'=>$priceRange->min_p,'max'=>$priceRange->max_p],
                    'suppliers'   => $suppliers,
                    'moq_ranges'  => $moqRanges,
                ],
            ],
        ]);
    }

    // ── GET /api/v1/ewholesale/products ──────────────────────────────────
    public function products(Request $r)
    {
        $filters = $r->only([
            'category_id','supplier_id','verification','moq_max',
            'price_min','price_max','origin','search','sort',
        ]);

        $paginator = $this->catalog->products($filters);

        $items = $paginator->getCollection()->map(fn($p) => $this->productCard($p));

        return response()->json([
            'data'  => $items,
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    // ── GET /api/v1/ewholesale/products/{slug} ────────────────────────────
    public function product(string $slug)
    {
        $p = EWProduct::with([
            'supplier:id,display_name,logo,banner,verification,rating,response_rate,response_time_avg,total_orders',
            'category:id,name,parent_id',
            'variants',
            'priceTiers',
            'activeDeals',
        ])->where('slug', $slug)->where('status', 'active')->firstOrFail();

        // Related products (same category, different product)
        $related = EWProduct::with('supplier:id,display_name,verification')
            ->where('category_id', $p->category_id)
            ->where('id', '!=', $p->id)
            ->where('status', 'active')
            ->limit(8)
            ->get()
            ->map(fn($r) => $this->productCard($r));

        // Tier ladder summary for display
        $tierSummary = $p->priceTiers->sortBy('min_qty')->map(fn($t) => [
            'min_qty'    => $t->min_qty,
            'max_qty'    => $t->max_qty,
            'unit_price' => $t->unit_price,
            'label'      => 'From $'.$t->unit_price.' / '.$p->unit.' at '.number_format($t->min_qty).'+',
        ]);

        $variantMatrix = $p->variants->where('is_active', true)->values()->map(fn($v) => [
            'id'         => $v->id,
            'sku'        => $v->sku,
            'attributes' => $v->attributes,
            'stock_qty'  => $v->stock_qty,
            'is_default' => $v->is_default,
            'weight_kg'  => $v->weight_kg,
            'volume_cbm' => $v->volume_cbm,
        ]);

        return response()->json([
            'data' => [
                'id'           => $p->id,
                'name'         => $p->name,
                'name_so'      => $p->name_so,
                'slug'         => $p->slug,
                'description'  => $p->description,
                'images'       => collect($p->images ?? [])->map(fn($img) => $this->mediaUrl($img)),
                'video_url'    => $p->video_url,
                'unit'         => $p->unit,
                'units_per_pack' => $p->units_per_pack,
                'moq'          => $p->moq,
                'lead_time_days' => $p->lead_time_days,
                'origin_country' => $p->origin_country,
                'brand'        => $p->brand,
                'specs'        => $p->specs,
                'tier_ladder'  => $tierSummary,
                'tier_summary_label' => $tierSummary->first() ? 'From $'.$p->min_price.' / '.$p->unit.' at '.$p->priceTiers->sortBy('min_qty')->first()?->min_qty.'+' : null,
                'min_price'    => $p->min_price,
                'max_price'    => $p->max_price,
                'active_deals' => $p->activeDeals->map(fn($d) => [
                    'percent_off' => $d->deal_price_percent_off,
                    'min_qty'     => $d->min_qty,
                    'ends_at_iso' => $d->ends_at->toIso8601String(),
                    'seconds_left'=> max(0, now()->diffInSeconds($d->ends_at, false)),
                ]),
                'variants'     => $variantMatrix,
                'supplier'     => [
                    'id'            => $p->supplier->id,
                    'display_name'  => $p->supplier->display_name,
                    'logo_url'      => $p->supplier->logo ? $this->mediaUrl($p->supplier->logo) : null,
                    'banner_url'    => $p->supplier->banner ? $this->mediaUrl($p->supplier->banner) : null,
                    'verification'  => $p->supplier->verification,
                    'rating'        => $p->supplier->rating,
                    'response_rate' => $p->supplier->response_rate,
                    'response_time_avg' => $p->supplier->response_time_avg,
                    'total_orders'  => $p->supplier->total_orders,
                ],
                'category'     => ['id'=>$p->category->id,'name'=>$p->category->name],
                'related'      => $related,
            ],
        ]);
    }

    // ── GET /api/v1/ewholesale/suppliers/{id} ────────────────────────────
    public function supplier(int $id)
    {
        $s = EWSupplier::findOrFail($id);
        $products = EWProduct::with('priceTiers')
            ->where('supplier_id', $s->id)->where('status','active')
            ->orderByDesc('orders_count')
            ->paginate(20);

        return response()->json([
            'data' => [
                'id'             => $s->id,
                'display_name'   => $s->display_name,
                'about'          => $s->about,
                'logo_url'       => $s->logo ? $this->mediaUrl($s->logo) : null,
                'banner_url'     => $s->banner ? $this->mediaUrl($s->banner) : null,
                'verification'   => $s->verification,
                'is_gold'        => $s->isGold(),
                'rating'         => $s->rating,
                'response_rate'  => $s->response_rate,
                'response_time_avg' => $s->response_time_avg,
                'total_orders'   => $s->total_orders,
                'products'       => $products->getCollection()->map(fn($p) => $this->productCard($p)),
                'products_meta'  => ['total'=>$products->total(),'last_page'=>$products->lastPage(),'current_page'=>$products->currentPage()],
            ],
        ]);
    }

    // ── GET /api/v1/ewholesale/search/suggest?q= ─────────────────────────
    // ── GET /api/v1/ewholesale/search ─────────────────────────────────────────
    public function search(Request $r)
    {
        $q    = trim($r->input('q', ''));
        $type = $r->input('type', 'all'); // all | products | suppliers
        $page = (int) $r->input('page', 1);
        $per  = 20;

        if (strlen($q) < 1) {
            return response()->json(['data' => ['products' => [], 'suppliers' => []], 'meta' => []]);
        }

        $products  = [];
        $suppliers = [];

        if ($type === 'all' || $type === 'products') {
            $productQuery = EWProduct::with(['priceTiers', 'supplier:id,display_name,verification'])
                ->where('status', 'active')
                ->where(fn($q2) => $q2->where('name', 'like', "%$q%")
                    ->orWhere('name_so', 'like', "%$q%")
                    ->orWhere('brand', 'like', "%$q%")
                    ->orWhere('tags', 'like', "%$q%"));

            $cat = $r->input('category_id');
            if ($cat) $productQuery->where('category_id', $cat);

            $paginator = $productQuery->orderByDesc('total_orders')->paginate($per, ['*'], 'page', $page);
            $products = [
                'data' => $paginator->getCollection()->map(fn($p) => $this->productCard($p)),
                'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
            ];
        }

        if ($type === 'all' || $type === 'suppliers') {
            $supplierQuery = EWSupplier::active()
                ->where(fn($q2) => $q2->where('display_name', 'like', "%$q%")
                    ->orWhere('about', 'like', "%$q%")
                    ->orWhere('specialty_tags', 'like', "%$q%"));

            $suppliers = $supplierQuery->limit(10)->get()->map(fn($s) => [
                'id'           => $s->id,
                'display_name' => $s->display_name,
                'logo_url'     => $s->logo ? $this->mediaUrl($s->logo) : null,
                'verification' => $s->verification,
                'rating'       => $s->rating,
                'total_orders' => $s->total_orders,
            ]);
        }

        return response()->json(['data' => ['products' => $products, 'suppliers' => $suppliers]]);
    }

    public function searchSuggest(Request $r)
    {
        $q = $r->input('q','');
        if (strlen($q) < 2) return response()->json(['data'=>[]]);

        $products = EWProduct::where('status','active')
            ->where(fn($q2) => $q2->where('name','like',"%$q%")->orWhere('brand','like',"%$q%"))
            ->limit(5)->get(['id','name','slug','unit','min_price']);

        $suppliers = EWSupplier::active()
            ->where('display_name','like',"%$q%")
            ->limit(3)->get(['id','display_name','verification']);

        $cats = EWCategory::active()
            ->where('name','like',"%$q%")
            ->limit(3)->get(['id','name','icon']);

        return response()->json([
            'data' => [
                'products'  => $products->map(fn($p) => ['id'=>$p->id,'name'=>$p->name,'slug'=>$p->slug,'from_price'=>$p->min_price,'unit'=>$p->unit]),
                'suppliers' => $suppliers->map(fn($s) => ['id'=>$s->id,'name'=>$s->display_name,'verified'=>$s->isVerified()]),
                'categories'=> $cats->map(fn($c) => ['id'=>$c->id,'name'=>$c->name,'icon'=>$c->icon]),
            ],
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────
    private function productCard(EWProduct $p): array
    {
        $firstTier = $p->priceTiers->sortBy('min_qty')->first();
        return [
            'id'           => $p->id,
            'name'         => $p->name,
            'name_so'      => $p->name_so,
            'slug'         => $p->slug,
            'first_image'  => $p->firstImage() ? $this->mediaUrl($p->firstImage()) : null,
            'unit'         => $p->unit,
            'moq'          => $p->moq,
            'min_price'    => $p->min_price,
            'max_price'    => $p->max_price,
            'tier_summary' => $firstTier ? 'From $'.$firstTier->unit_price.' / '.$p->unit.' at '.number_format($firstTier->min_qty).'+' : null,
            'lead_time_days' => $p->lead_time_days,
            'origin_country' => $p->origin_country,
            'supplier_name'    => $p->supplier?->display_name,
            'supplier_verified'=> $p->supplier?->isVerified(),
            'orders_count'   => $p->orders_count,
        ];
    }

    private function mediaUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http')) return $path;
        return config('app.url') . '/api/v1/media?f=' . ltrim($path, '/');
    }
}
