<?php

namespace App\Http\Controllers\Api\EGrocery;

use App\Http\Controllers\Controller;
use App\Models\EGrocery\{EGroceryBanner, EGroceryCategory, EGrocerySection, EGroceryDeliveryZone, EGroceryOrder, EGroceryProduct};
use App\Services\EGrocery\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class EGroceryHomeController extends Controller
{
    public function __construct(private PricingService $pricing) {}

    /**
     * GET /api/v1/egrocery/home
     * One aggregated, cached payload for the home screen.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        // Core payload (public, cached 5 min)
        $payload = Cache::remember('egrocery:home:v1', 300, fn () => $this->buildBasePayload());

        // Delivery banner — personalised if user has a district
        $deliveryInfo = $this->buildDeliveryInfo($request);

        // Buy Again rail — only when authenticated
        $buyAgain = $userId ? $this->buildBuyAgain($userId) : null;

        return response()->json([
            'success' => true,
            'data'    => array_merge($payload, [
                'delivery_info' => $deliveryInfo,
                'buy_again'     => $buyAgain,
            ]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function buildBasePayload(): array
    {
        // Banners (active, by placement)
        $banners = EGroceryBanner::active()
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($b) => [
                'id'         => $b->id,
                'image'      => $b->image,
                'placement'  => $b->placement,
                'link_type'  => $b->link_type,
                'link_value' => $b->link_value,
            ])
            ->groupBy('placement');

        // Category grid (top-level, active only)
        $categories = EGroceryCategory::whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id','name','name_so','slug','icon','image']);

        // Sections (active, with products resolved)
        $sections = EGrocerySection::active()
            ->with(['products' => fn ($q) => $q->active()->with('activeVariants')])
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($s) => $this->transformSection($s));

        return [
            'banners'    => $banners,
            'categories' => $categories,
            'sections'   => $sections,
        ];
    }

    private function transformSection(EGrocerySection $section): array
    {
        $products = match ($section->type) {
            'best_sellers'  => $this->fetchBestSellers($section->category_id),
            'new_arrivals'  => $this->fetchNewArrivals($section->category_id),
            'flash_deal'    => $this->fetchFlashDealProducts($section),
            default         => $section->products,
        };

        // Batch-price all variants in this section
        $allVariants = $products->flatMap(fn ($p) => $p->activeVariants ?? $p->variants ?? collect());
        $prices = $this->pricing->resolveMany($allVariants);

        // Flash deal meta keyed by variant_id
        $flashMeta = [];
        if ($section->type === 'flash_deal') {
            $section->flashDeals->each(function ($fd) use (&$flashMeta, $section) {
                $flashMeta[$fd->variant_id] = [
                    'deal_price' => $fd->deal_price,
                    'qty_limit'  => $fd->qty_limit,
                    'qty_sold'   => $fd->qty_sold,
                    'qty_left'   => $fd->qty_limit ? max(0, $fd->qty_limit - $fd->qty_sold) : null,
                    'ends_at'    => $section->ends_at?->toIso8601String(),
                ];
            });
        }

        return [
            'id'      => $section->id,
            'title'   => $section->title,
            'title_so'=> $section->title_so,
            'type'    => $section->type,
            'layout'  => $section->layout,
            'ends_at' => $section->ends_at?->toIso8601String(),
            'products'=> $products->map(fn ($p) => $this->transformProductCard($p, $prices, $flashMeta)),
        ];
    }

    private function fetchBestSellers(?int $categoryId): \Illuminate\Support\Collection
    {
        return EGroceryProduct::active()->inStock()
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->with('activeVariants')
            ->orderByDesc('orders_count')
            ->limit(12)
            ->get();
    }

    private function fetchNewArrivals(?int $categoryId): \Illuminate\Support\Collection
    {
        return EGroceryProduct::active()->inStock()
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->with('activeVariants')
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();
    }

    private function fetchFlashDealProducts(EGrocerySection $section): \Illuminate\Support\Collection
    {
        $section->load(['flashDeals.variant.product' => fn ($q) => $q->with('activeVariants')]);

        return $section->flashDeals
            ->filter(fn ($fd) => $fd->variant?->product)
            ->map(fn ($fd) => tap($fd->variant->product, fn ($p) => $p->setRelation('activeVariants', $p->activeVariants)))
            ->unique('id')
            ->values();
    }

    private function transformProductCard(EGroceryProduct $product, array $prices, array $flashMeta): array
    {
        $variants = ($product->activeVariants ?? $product->variants ?? collect())->map(function ($v) use ($prices, $flashMeta) {
            $price = $prices[$v->id] ?? ['effective_price' => $v->price, 'original_price' => null, 'discount_pct' => 0, 'is_flash_deal' => false];
            return array_merge([
                'id'        => $v->id,
                'label'     => $v->label,
                'stock_qty' => (float) $v->stock_qty,
                'in_stock'  => $v->isInStock(),
                'is_default'=> (bool) $v->is_default,
            ], $price, $flashMeta[$v->id] ?? []);
        });

        return [
            'id'         => $product->id,
            'name'       => $product->name,
            'name_so'    => $product->name_so,
            'slug'       => $product->slug,
            'image'      => $product->first_image,
            'avg_rating' => (float) $product->avg_rating,
            'variants'   => $variants,
        ];
    }

    private function buildDeliveryInfo(Request $request): array
    {
        $user = $request->user();
        $districtId = $user?->district_id;

        $zone = null;
        if ($districtId) {
            // Find zone that covers user's district
            $zone = EGroceryDeliveryZone::where('is_active', true)->get()
                ->first(fn ($z) => in_array($districtId, (array)($z->district_ids ?? [])));
        }

        if (!$zone) {
            $zone = EGroceryDeliveryZone::where('is_active', true)->first();
        }

        $bonusAmount = \App\Services\DeliveryBonusService::getActiveBonusAmount();

        return $zone ? [
            'delivery_fee' => round((float) $zone->delivery_fee + $bonusAmount, 2),
            'min_order'    => (float) $zone->min_order,
            'free_over'    => $zone->free_over ? (float) $zone->free_over : null,
            'zone_name'    => $zone->name,
        ] : [
            'delivery_fee' => round(2.00 + $bonusAmount, 2),
            'min_order'    => 0.00,
            'free_over'    => null,
            'zone_name'    => null,
        ];
    }

    private function buildBuyAgain(int $userId): array
    {
        $variantIds = EGroceryOrder::where('user_id', $userId)
            ->where('status', 'delivered')
            ->latest()
            ->limit(5)
            ->get()
            ->flatMap(fn ($o) => $o->items->pluck('variant_id'))
            ->unique()
            ->take(12);

        if ($variantIds->isEmpty()) return [];

        $products = EGroceryProduct::active()
            ->whereHas('activeVariants', fn ($q) => $q->whereIn('id', $variantIds))
            ->with(['activeVariants' => fn ($q) => $q->whereIn('id', $variantIds)])
            ->limit(12)
            ->get();

        $prices = $this->pricing->resolveMany($products->flatMap(fn ($p) => $p->activeVariants));

        return $products->map(fn ($p) => $this->transformProductCard($p, $prices, []))->values()->toArray();
    }
}
