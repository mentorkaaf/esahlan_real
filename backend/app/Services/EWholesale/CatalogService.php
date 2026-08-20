<?php

namespace App\Services\EWholesale;

use App\Models\EWholesale\{EWCategory, EWProduct};
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CatalogService
{
    private const CACHE_TTL = 120; // seconds

    // ── Category Tree ─────────────────────────────────────────────────────

    /** Full active category tree (2 levels). Redis-cached. */
    public function categoryTree(): Collection
    {
        return Cache::remember('ew:category_tree', self::CACHE_TTL, function () {
            return EWCategory::active()
                ->roots()
                ->with(['children' => fn($q) => $q->active()->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get();
        });
    }

    /** Flat list of all descendant category IDs for a given root (includes self). */
    public function descendantIds(int $categoryId): array
    {
        $cat = EWCategory::with('children')->find($categoryId);
        return $cat ? $cat->descendantIds() : [$categoryId];
    }

    // ── Product Query Builder ──────────────────────────────────────────────

    /**
     * Paginated product listing with rich filters.
     *
     * Supported filters (all optional):
     *  category_id       int    — include children
     *  supplier_id       int
     *  verification      string — 'verified','gold' or null
     *  moq_max           float  — products with moq ≤ this
     *  price_min/max     float  — on min_price
     *  origin            string
     *  search            string — name, name_so, specs JSON
     *  sort              string — relevance|price_asc|price_desc|moq_asc|orders|newest
     *  per_page          int    — default 24
     */
    public function products(array $filters = []): LengthAwarePaginator
    {
        $q = EWProduct::active()
            ->with(['supplier:id,display_name,logo,verification', 'defaultVariant', 'priceTiers'])
            ->select('ewholesale_products.*');

        // ── Category filter (include descendants) ─────────────────────────
        if (!empty($filters['category_id'])) {
            $ids = $this->descendantIds((int)$filters['category_id']);
            $q->whereIn('category_id', $ids);
        }

        // ── Supplier filter ───────────────────────────────────────────────
        if (!empty($filters['supplier_id'])) {
            $q->where('supplier_id', $filters['supplier_id']);
        }

        // ── Supplier verification level filter ────────────────────────────
        if (!empty($filters['verification'])) {
            $q->whereHas('supplier', fn($sq) =>
                $sq->where('verification', $filters['verification'])->where('is_active', true)
            );
        } else {
            // Always require active supplier
            $q->whereHas('supplier', fn($sq) => $sq->where('is_active', true));
        }

        // ── MOQ cap ───────────────────────────────────────────────────────
        if (!empty($filters['moq_max'])) {
            $q->where('moq', '<=', $filters['moq_max']);
        }

        // ── Price range on min_price ──────────────────────────────────────
        if (!empty($filters['price_min'])) {
            $q->where('min_price', '>=', $filters['price_min']);
        }
        if (!empty($filters['price_max'])) {
            $q->where('min_price', '<=', $filters['price_max']);
        }

        // ── Origin country ────────────────────────────────────────────────
        if (!empty($filters['origin'])) {
            $q->where('origin_country', $filters['origin']);
        }

        // ── Full-text search ──────────────────────────────────────────────
        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $q->where(function ($sq) use ($term) {
                $sq->where('name', 'like', $term)
                   ->orWhere('name_so', 'like', $term)
                   ->orWhere('brand', 'like', $term)
                   ->orWhereRaw("JSON_SEARCH(specs, 'all', ?) IS NOT NULL", [$filters['search']]);
            });
        }

        // ── Sorting ───────────────────────────────────────────────────────
        match ($filters['sort'] ?? 'relevance') {
            'price_asc'  => $q->orderBy('min_price'),
            'price_desc' => $q->orderByDesc('min_price'),
            'moq_asc'    => $q->orderBy('moq'),
            'orders'     => $q->orderByDesc('orders_count'),
            'newest'     => $q->latest(),
            default      => $q->orderByDesc('is_featured')->orderByDesc('orders_count'),
        };

        return $q->paginate($filters['per_page'] ?? 24);
    }

    // ── Featured / Home ────────────────────────────────────────────────────

    /** Homepage: featured products, Redis-cached. */
    public function featured(int $limit = 12): Collection
    {
        return Cache::remember("ew:featured:{$limit}", self::CACHE_TTL, function () use ($limit) {
            return EWProduct::active()
                ->featured()
                ->with(['supplier:id,display_name,logo,verification', 'defaultVariant', 'priceTiers'])
                ->orderByDesc('orders_count')
                ->limit($limit)
                ->get();
        });
    }

    /** Flush catalog caches (call after product/category changes). */
    public function flushCache(): void
    {
        Cache::forget('ew:category_tree');
        Cache::forget('ew:featured:12');
    }
}
