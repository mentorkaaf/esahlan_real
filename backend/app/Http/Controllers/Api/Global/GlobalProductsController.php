<?php

namespace App\Http\Controllers\Api\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalProduct;
use App\Models\Global\GlobalCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class GlobalProductsController extends Controller
{
    public function index(Request $request)
    {
        $query = GlobalProduct::with(['category', 'images'])
            ->where('is_active', true);

        if ($request->q) {
            $q = $request->q;
            $query->where(function ($b) use ($q) {
                $b->where('name', 'like', "%$q%")
                  ->orWhere('description', 'like', "%$q%")
                  ->orWhere('tags', 'like', "%$q%");
            });
        }

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->min_price) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->max_price) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        $sort = $request->sort ?? 'newest';
        match($sort) {
            'price_asc'  => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'popular'    => $query->orderBy('views_count', 'desc'),
            default      => $query->orderBy('created_at', 'desc'),
        };

        $products = $query->paginate(24);

        return response()->json([
            'products' => $products->through(fn($p) => $this->productCard($p)),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'per_page'     => $products->perPage(),
                'total'        => $products->total(),
            ],
        ]);
    }

    public function show($id)
    {
        $product = GlobalProduct::with(['category', 'images', 'variants'])
            ->where('is_active', true)
            ->findOrFail($id);

        $product->increment('views_count');

        // Related products
        $related = GlobalProduct::where('category_id', $product->category_id)
            ->where('id', '!=', $id)
            ->where('is_active', true)
            ->limit(8)
            ->get()
            ->map(fn($p) => $this->productCard($p));

        return response()->json([
            'product' => $this->productDetail($product),
            'related' => $related,
        ]);
    }

    public function categories()
    {
        $categories = Cache::remember('global_categories', 600, function () {
            return GlobalCategory::withCount(['products' => fn($q) => $q->where('is_active', true)])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        });

        return response()->json(['categories' => $categories]);
    }

    public function featured()
    {
        $products = Cache::remember('global_featured', 300, function () {
            return GlobalProduct::with(['category', 'images'])
                ->where('is_active', true)
                ->where('is_featured', true)
                ->orderBy('created_at', 'desc')
                ->limit(12)
                ->get()
                ->map(fn($p) => $this->productCard($p));
        });

        return response()->json(['products' => $products]);
    }

    public function flash()
    {
        $products = GlobalProduct::with(['images'])
            ->where('is_active', true)
            ->whereNotNull('compare_price')
            ->where('compare_price', '>', \DB::raw('price'))
            ->orderByRaw('(compare_price - price) DESC')
            ->limit(10)
            ->get()
            ->map(fn($p) => $this->productCard($p));

        return response()->json(['products' => $products]);
    }

    private function productCard(GlobalProduct $p): array
    {
        $images = $p->images ?? collect();
        $thumb  = $images->first()?->url ?? $p->thumbnail;

        return [
            'id'            => $p->id,
            'name'          => $p->name,
            'slug'          => $p->slug,
            'price'         => $p->price,
            'compare_price' => $p->compare_price,
            'discount_pct'  => $p->compare_price > 0
                ? round((1 - $p->price / $p->compare_price) * 100)
                : null,
            'thumbnail'     => $thumb,
            'rating'        => $p->rating_avg ?? 0,
            'reviews_count' => $p->reviews_count ?? 0,
            'is_featured'   => $p->is_featured,
            'type'          => $p->type,
            'category'      => $p->category?->name,
        ];
    }

    private function productDetail(GlobalProduct $p): array
    {
        return array_merge($this->productCard($p), [
            'description'   => $p->description,
            'sku'           => $p->sku,
            'weight'        => $p->weight,
            'stock'         => $p->track_stock ? $p->stock : null,
            'in_stock'      => !$p->track_stock || $p->stock > 0,
            'images'        => $p->images?->pluck('url') ?? [$p->thumbnail],
            'variants'      => $p->variants ?? [],
            'tags'          => $p->tags ? explode(',', $p->tags) : [],
        ]);
    }
}
