<?php

namespace App\Http\Controllers\Api\EGrocery;

use App\Http\Controllers\Controller;
use App\Models\EGrocery\{EGroceryCategory, EGroceryProduct, EGroceryBrand};
use App\Services\EGrocery\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class EGroceryCatalogController extends Controller
{
    public function __construct(private PricingService $pricing) {}

    // ── Categories ────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/egrocery/categories
     * Top-level categories with child count.
     */
    public function categories(): JsonResponse
    {
        $cats = Cache::remember('egrocery:categories:v1', 600, fn () =>
            EGroceryCategory::whereNull('parent_id')
                ->where('is_active', true)
                ->withCount('children')
                ->orderBy('sort_order')
                ->get(['id','name','name_so','slug','icon','image'])
                ->map(fn ($c) => [
                    'id'           => $c->id,
                    'name'         => $c->name,
                    'name_so'      => $c->name_so,
                    'slug'         => $c->slug,
                    'icon'         => $c->icon,
                    'image'        => $c->image,
                    'children_count' => $c->children_count,
                ])
        );

        return response()->json(['success' => true, 'data' => $cats]);
    }

    /**
     * GET /api/v1/egrocery/categories/{id}
     * Category detail with children + facets.
     */
    public function category(int $id): JsonResponse
    {
        $cat = EGroceryCategory::with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->findOrFail($id);

        // Facets: brands and price range for products in this category tree
        $categoryIds = collect([$id])->merge($cat->children->pluck('id'))->all();

        $facets = Cache::remember("egrocery:category:{$id}:facets", 300, function () use ($categoryIds) {
            $brands = EGroceryBrand::whereHas('products', fn ($q) => $q->whereIn('category_id', $categoryIds)->where('is_active', true))
                ->where('is_active', true)
                ->get(['id','name']);

            $priceRange = \DB::table('egrocery_product_variants as v')
                ->join('egrocery_products as p', 'p.id', '=', 'v.product_id')
                ->whereIn('p.category_id', $categoryIds)
                ->where('p.is_active', true)
                ->where('v.is_active', true)
                ->selectRaw('MIN(v.price) as min_price, MAX(v.price) as max_price')
                ->first();

            return [
                'brands'     => $brands,
                'min_price'  => $priceRange?->min_price ? (float) $priceRange->min_price : 0,
                'max_price'  => $priceRange?->max_price ? (float) $priceRange->max_price : 999,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'id'       => $cat->id,
                'name'     => $cat->name,
                'name_so'  => $cat->name_so,
                'slug'     => $cat->slug,
                'icon'     => $cat->icon,
                'image'    => $cat->image,
                'children' => $cat->children->map(fn ($c) => [
                    'id'     => $c->id,
                    'name'   => $c->name,
                    'name_so'=> $c->name_so,
                    'slug'   => $c->slug,
                    'icon'   => $c->icon,
                ]),
                'facets' => $facets,
            ],
        ]);
    }

    // ── Products ──────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/egrocery/products
     * Filterable, paginated product listing.
     */
    public function products(Request $request): JsonResponse
    {
        $q = EGroceryProduct::active()
            ->with(['activeVariants', 'brand:id,name', 'category:id,name,name_so'])
            ->withCount('reviews');

        // Filters
        if ($request->category) {
            $cat = is_numeric($request->category)
                ? EGroceryCategory::find($request->category)
                : EGroceryCategory::where('slug', $request->category)->first();
            if ($cat) {
                $ids = EGroceryCategory::where('parent_id', $cat->id)->pluck('id')->prepend($cat->id);
                $q->whereIn('category_id', $ids);
            }
        }

        if ($request->brand)      $q->where('brand_id', $request->brand);
        if ($request->in_stock)   $q->inStock();
        if ($request->discounted) $q->whereHas('activeVariants', fn ($v) => $v->whereNotNull('compare_price')->whereColumn('price', '<', 'compare_price'));

        if ($request->min_price || $request->max_price) {
            $q->whereHas('activeVariants', function ($v) use ($request) {
                if ($request->min_price) $v->where('price', '>=', $request->min_price);
                if ($request->max_price) $v->where('price', '<=', $request->max_price);
            });
        }

        if ($request->search) {
            $s = '%' . $request->search . '%';
            $q->where(fn ($x) => $x->where('name', 'like', $s)->orWhere('name_so', 'like', $s)->orWhere('tags', 'like', $s));
        }

        // Sort
        match ($request->sort) {
            'price_asc'  => $q->orderByRaw('(SELECT MIN(price) FROM egrocery_product_variants WHERE product_id = egrocery_products.id AND is_active=1) ASC'),
            'price_desc' => $q->orderByRaw('(SELECT MIN(price) FROM egrocery_product_variants WHERE product_id = egrocery_products.id AND is_active=1) DESC'),
            'newest'     => $q->orderByDesc('created_at'),
            'rating'     => $q->orderByDesc('avg_rating'),
            'popular'    => $q->orderByDesc('orders_count'),
            default      => $q->orderByDesc('is_featured')->orderByDesc('orders_count'),
        };

        $paginator = $q->paginate(24);
        $allVariants = $paginator->getCollection()->flatMap(fn ($p) => $p->activeVariants);
        $prices = $this->pricing->resolveMany($allVariants);

        return response()->json([
            'success' => true,
            'data'    => $paginator->getCollection()->map(fn ($p) => $this->transformCard($p, $prices)),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/egrocery/products/{slug}
     * Full product detail with variants, reviews, related.
     */
    public function product(string $slug, Request $request): JsonResponse
    {
        $product = EGroceryProduct::where('slug', $slug)
            ->where('is_active', true)
            ->with([
                'category:id,name,name_so,slug',
                'brand:id,name,logo',
                'activeVariants.unit',
                'reviews' => fn ($q) => $q->where('is_approved', true)->with('user:id,name,avatar')->latest()->take(5),
            ])
            ->withCount(['reviews' => fn ($q) => $q->where('is_approved', true)])
            ->firstOrFail();

        $prices = $this->pricing->resolveMany($product->activeVariants);

        // Flash deal qty_left per variant
        $flashDeals = \App\Models\EGrocery\EGroceryFlashDeal::whereIn('variant_id', $product->activeVariants->pluck('id'))
            ->whereHas('section', fn ($q) => $q->active())
            ->get()->keyBy('variant_id');

        $variants = $product->activeVariants->map(function ($v) use ($prices, $flashDeals) {
            $fd = $flashDeals->get($v->id);
            return array_merge([
                'id'         => $v->id,
                'label'      => $v->label,
                'sku'        => $v->sku,
                'stock_qty'  => (float) $v->stock_qty,
                'in_stock'   => $v->isInStock(),
                'is_default' => (bool) $v->is_default,
                'unit'       => $v->unit?->only(['id','name','abbreviation']),
                'unit_qty'   => (float) $v->unit_qty,
                'flash_deal' => $fd ? [
                    'qty_limit' => $fd->qty_limit,
                    'qty_sold'  => $fd->qty_sold,
                    'qty_left'  => $fd->qty_limit ? max(0, $fd->qty_limit - $fd->qty_sold) : null,
                ] : null,
            ], $prices[$v->id] ?? []);
        });

        // Related: same category, best sellers, exclude current
        $related = EGroceryProduct::active()->inStock()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with('activeVariants')
            ->orderByDesc('orders_count')
            ->limit(10)
            ->get();
        $relatedPrices = $this->pricing->resolveMany($related->flatMap(fn ($p) => $p->activeVariants));

        return response()->json([
            'success' => true,
            'data'    => [
                'id'          => $product->id,
                'name'        => $product->name,
                'name_so'     => $product->name_so,
                'slug'        => $product->slug,
                'description' => $product->description,
                'images'      => $product->images ?? [],
                'category'    => $product->category,
                'brand'       => $product->brand,
                'avg_rating'  => (float) $product->avg_rating,
                'reviews_count'=> $product->reviews_count,
                'is_weight_based' => (bool) $product->is_weight_based,
                'variants'    => $variants,
                'reviews'     => $product->reviews->map(fn ($r) => [
                    'id'      => $r->id,
                    'rating'  => $r->rating,
                    'comment' => $r->comment,
                    'user'    => ['name' => $r->user?->name, 'avatar' => $r->user?->avatar],
                    'date'    => $r->created_at->diffForHumans(),
                ]),
                'related' => $related->map(fn ($p) => $this->transformCard($p, $relatedPrices)),
            ],
        ]);
    }

    // ── Search ────────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/egrocery/search/suggest?q=
     */
    public function suggest(Request $request): JsonResponse
    {
        $q = trim($request->q ?? '');
        if (strlen($q) < 2) return response()->json(['success' => true, 'data' => []]);

        $cacheKey = 'egrocery:suggest:' . md5(strtolower($q));
        $results = Cache::remember($cacheKey, 30, function () use ($q) {
            $like = '%' . $q . '%';
            $products = EGroceryProduct::active()
                ->where(fn ($x) => $x->where('name', 'like', $like)->orWhere('name_so', 'like', $like))
                ->select('id','name','slug')
                ->limit(6)
                ->get()
                ->map(fn ($p) => ['type' => 'product', 'id' => $p->id, 'label' => $p->name, 'slug' => $p->slug]);

            $cats = EGroceryCategory::where('is_active', true)
                ->where(fn ($x) => $x->where('name', 'like', $like)->orWhere('name_so', 'like', $like))
                ->select('id','name','slug')
                ->limit(3)
                ->get()
                ->map(fn ($c) => ['type' => 'category', 'id' => $c->id, 'label' => $c->name, 'slug' => $c->slug]);

            return $products->merge($cats)->values();
        });

        return response()->json(['success' => true, 'data' => $results]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function transformCard(EGroceryProduct $product, array $prices): array
    {
        $variants = $product->activeVariants->map(fn ($v) => array_merge([
            'id'        => $v->id,
            'label'     => $v->label,
            'stock_qty' => (float) $v->stock_qty,
            'in_stock'  => $v->isInStock(),
            'is_default'=> (bool) $v->is_default,
        ], $prices[$v->id] ?? ['effective_price' => $v->price, 'original_price' => null, 'discount_pct' => 0, 'is_flash_deal' => false]));

        return [
            'id'         => $product->id,
            'name'       => $product->name,
            'name_so'    => $product->name_so,
            'slug'       => $product->slug,
            'image'      => $product->first_image,
            'avg_rating' => (float) $product->avg_rating,
            'brand'      => $product->brand?->name,
            'category'   => $product->category?->only(['id','name']),
            'variants'   => $variants,
        ];
    }
}
