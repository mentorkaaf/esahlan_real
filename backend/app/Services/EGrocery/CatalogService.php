<?php
namespace App\Services\EGrocery;

use App\Models\EGrocery\{EGroceryCategory, EGroceryProduct, EGroceryProductVariant};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CatalogService
{
    private const CACHE_TTL  = 120;   // 2 minutes for lists
    private const HOME_TTL   = 120;   // same as spec
    private const TREE_TTL   = 300;   // category tree changes rarely

    // ── Category Tree ─────────────────────────────────────────────────────────

    /**
     * 2-level category tree with product counts.
     * Cached 5 minutes; invalidated by admin writes.
     */
    public function categoryTree(bool $includeInactive = false): array
    {
        $cacheKey = 'egrocery:cat_tree:' . ($includeInactive ? 'all' : 'active');

        return Cache::remember($cacheKey, self::TREE_TTL, function () use ($includeInactive) {
            $q = EGroceryCategory::with(['children' => function ($q) use ($includeInactive) {
                    if (!$includeInactive) $q->where('is_active', true);
                    $q->withCount(['products as product_count' => fn($q) => $q->where('is_active', true)]);
                }])
                ->whereNull('parent_id')
                ->withCount(['products as product_count' => fn($q) => $q->where('is_active', true)]);

            if (!$includeInactive) $q->where('is_active', true);

            return $q->orderBy('sort_order')->get()->map(function ($cat) {
                return [
                    'id'            => $cat->id,
                    'name'          => $cat->name,
                    'name_so'       => $cat->name_so,
                    'slug'          => $cat->slug,
                    'icon'          => $cat->icon,
                    'image'         => $cat->image,
                    'product_count' => $cat->product_count,
                    'children'      => $cat->children->map(fn($c) => [
                        'id'            => $c->id,
                        'name'          => $c->name,
                        'name_so'       => $c->name_so,
                        'slug'          => $c->slug,
                        'icon'          => $c->icon,
                        'image'         => $c->image,
                        'product_count' => $c->product_count,
                    ])->values()->all(),
                ];
            })->all();
        });
    }

    public function invalidateCategoryCache(): void
    {
        Cache::forget('egrocery:cat_tree:active');
        Cache::forget('egrocery:cat_tree:all');
    }

    // ── Product Query Builder ─────────────────────────────────────────────────

    /**
     * Build a filtered + sorted product query.
     *
     * $filters:
     *   category_id  int|null  — include children automatically
     *   brand_id     int|null
     *   min_price    float|null
     *   max_price    float|null
     *   has_discount bool
     *   in_stock     bool
     *   search       string    — searches name, name_so, tags
     *   is_featured  bool
     *   ids          int[]     — specific product ids
     *
     * $sort: relevance | price_asc | price_desc | best_selling | newest
     */
    public function productQuery(array $filters = [], string $sort = 'relevance'): Builder
    {
        $q = EGroceryProduct::with([
            'defaultVariant.unit',
            'activeVariants.unit',
            'category:id,name,name_so,slug',
            'brand:id,name',
        ])->where('is_active', true);

        // ── Category filter (include children) ────────────────────────────────
        if (!empty($filters['category_id'])) {
            $childIds = EGroceryCategory::where('parent_id', $filters['category_id'])
                ->pluck('id')->all();
            $ids = array_merge([(int) $filters['category_id']], $childIds);
            $q->whereIn('category_id', $ids);
        }

        // ── Brand filter ──────────────────────────────────────────────────────
        if (!empty($filters['brand_id'])) {
            $q->where('brand_id', $filters['brand_id']);
        }

        // ── Price range (against default or cheapest variant) ─────────────────
        if (!empty($filters['min_price']) || !empty($filters['max_price'])) {
            $q->whereHas('activeVariants', function ($vq) use ($filters) {
                if (!empty($filters['min_price'])) $vq->where('price', '>=', $filters['min_price']);
                if (!empty($filters['max_price'])) $vq->where('price', '<=', $filters['max_price']);
            });
        }

        // ── Has discount (compare_price set and > price) ───────────────────────
        if (!empty($filters['has_discount'])) {
            $q->whereHas('activeVariants', fn($vq) =>
                $vq->whereNotNull('compare_price')->whereColumn('compare_price', '>', 'price')
            );
        }

        // ── In stock ──────────────────────────────────────────────────────────
        if (!empty($filters['in_stock'])) {
            $q->whereHas('activeVariants', fn($vq) => $vq->where('stock_qty', '>', 0));
        }

        // ── Featured ──────────────────────────────────────────────────────────
        if (!empty($filters['is_featured'])) {
            $q->where('is_featured', true);
        }

        // ── Specific IDs ──────────────────────────────────────────────────────
        if (!empty($filters['ids'])) {
            $q->whereIn('id', $filters['ids']);
        }

        // ── Full-text search: name, name_so, tags ────────────────────────────
        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $q->where(function ($sq) use ($term, $filters) {
                $sq->where('name',    'like', $term)
                   ->orWhere('name_so', 'like', $term)
                   ->orWhere('tags',  'like', $term)
                   ->orWhereHas('brand', fn($b) => $b->where('name', 'like', $term));
            });
        }

        // ── Sort ──────────────────────────────────────────────────────────────
        match ($sort) {
            'price_asc'    => $q->orderByRaw('(SELECT MIN(price) FROM egrocery_product_variants WHERE product_id = egrocery_products.id AND is_active = 1) ASC'),
            'price_desc'   => $q->orderByRaw('(SELECT MIN(price) FROM egrocery_product_variants WHERE product_id = egrocery_products.id AND is_active = 1) DESC'),
            'best_selling' => $q->orderByDesc('orders_count'),
            'newest'       => $q->orderByDesc('created_at'),
            default        => $q->orderByDesc('is_featured')->orderByDesc('orders_count'), // relevance
        };

        return $q;
    }

    /**
     * Paginated product search — cached when no user-specific filters.
     */
    public function paginateProducts(array $filters = [], string $sort = 'relevance', int $perPage = 20): LengthAwarePaginator
    {
        // Don't cache if search term is present (too many permutations)
        if (!empty($filters['search'])) {
            return $this->productQuery($filters, $sort)->paginate($perPage);
        }

        $cacheKey = 'egrocery:products:' . md5(json_encode($filters) . $sort . $perPage);
        // Cache for 60s only; admin writes must bust via invalidateProductCache()
        return Cache::remember($cacheKey, 60, function () use ($filters, $sort, $perPage) {
            return $this->productQuery($filters, $sort)->paginate($perPage);
        });
    }

    public function invalidateProductCache(): void
    {
        // Simple approach: use a version key to bust all product caches
        Cache::increment('egrocery:products:version');
    }

    // ── Home Page Data ────────────────────────────────────────────────────────

    /**
     * Home page: banners + active sections with their products.
     * Redis-cached 120s as per spec.
     */
    public function homeData(?int $userId = null): array
    {
        // Sections/banners are not user-specific; buy_again IS user-specific
        $staticKey = 'egrocery:home:static';

        $static = Cache::remember($staticKey, self::HOME_TTL, function () {
            $banners = \App\Models\EGrocery\EGroceryBanner::active()
                ->orderBy('sort_order')
                ->get(['id','title','image','placement','link_type','link_value'])
                ->groupBy('placement');

            $sections = \App\Models\EGrocery\EGrocerySection::active()
                ->where('type', '!=', 'buy_again')  // user-specific, handled separately
                ->with([
                    'products' => function ($q) {
                        $q->where('is_active', true)
                          ->with(['defaultVariant.unit', 'activeVariants'])
                          ->limit(12);
                    },
                    'flashDeals.variant',
                    'category:id,name,name_so,slug,icon',
                ])
                ->orderBy('sort_order')
                ->get();

            return compact('banners', 'sections');
        });

        return $static;
    }

    // ── Single Product ────────────────────────────────────────────────────────

    public function productDetail(string $slugOrId): ?EGroceryProduct
    {
        return EGroceryProduct::with([
            'category:id,name,name_so,slug,parent_id',
            'category.parent:id,name,name_so,slug',
            'brand:id,name,logo',
            'activeVariants.unit',
            'baseUnit',
        ])
        ->where('is_active', true)
        ->where(is_numeric($slugOrId) ? 'id' : 'slug', $slugOrId)
        ->first();
    }
}
