<?php

namespace App\Http\Controllers\Api\EGrocery;

use App\Http\Controllers\Controller;
use App\Models\EGrocery\{
    EGroceryFavorite, EGroceryProduct, EGroceryProductVariant,
    EGroceryShoppingList, EGroceryShoppingListItem,
    EGroceryDeliveryZone, EGroceryFlashDeal
};
use App\Models\{Coupon, CouponUsage};
use App\Services\EGrocery\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class EGroceryUserController extends Controller
{
    public function __construct(private PricingService $pricing) {}

    // ── Favorites ─────────────────────────────────────────────────────────────

    public function favorites(Request $request): JsonResponse
    {
        $favs = EGroceryFavorite::where('user_id', $request->user()->id)
            ->with(['product' => fn ($q) => $q->active()->with('activeVariants')])
            ->latest()
            ->get()
            ->filter(fn ($f) => $f->product)
            ->values();

        $allVariants = $favs->flatMap(fn ($f) => $f->product->activeVariants);
        $prices = $this->pricing->resolveMany($allVariants);

        return response()->json([
            'success' => true,
            'data'    => $favs->map(fn ($f) => $this->transformProductCard($f->product, $prices)),
        ]);
    }

    public function addFavorite(Request $request): JsonResponse
    {
        $request->validate(['product_id' => 'required|integer|exists:egrocery_products,id']);

        EGroceryFavorite::firstOrCreate([
            'user_id'    => $request->user()->id,
            'product_id' => $request->product_id,
        ]);

        return response()->json(['success' => true, 'message' => 'Added to favorites']);
    }

    public function removeFavorite(Request $request, int $productId): JsonResponse
    {
        EGroceryFavorite::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->delete();

        return response()->json(['success' => true, 'message' => 'Removed from favorites']);
    }

    // ── Shopping Lists ────────────────────────────────────────────────────────

    public function lists(Request $request): JsonResponse
    {
        $lists = EGroceryShoppingList::where('user_id', $request->user()->id)
            ->withCount('items')
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $lists]);
    }

    public function createList(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:120']);

        $list = EGroceryShoppingList::create([
            'user_id' => $request->user()->id,
            'name'    => $request->name,
        ]);

        return response()->json(['success' => true, 'data' => $list], 201);
    }

    public function showList(Request $request, int $id): JsonResponse
    {
        $list = EGroceryShoppingList::where('user_id', $request->user()->id)
            ->with(['items.product' => fn ($q) => $q->with('activeVariants'), 'items.variant'])
            ->findOrFail($id);

        $allVariants = $list->items->flatMap(fn ($i) => $i->product?->activeVariants ?? collect());
        $prices = $this->pricing->resolveMany($allVariants);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'    => $list->id,
                'name'  => $list->name,
                'items' => $list->items->map(fn ($i) => [
                    'id'      => $i->id,
                    'product' => $i->product ? $this->transformProductCard($i->product, $prices) : null,
                    'variant_id' => $i->variant_id,
                    'variant' => $i->variant?->only(['id','label','price']),
                    'qty'     => $i->qty,
                    'checked' => (bool) $i->checked,
                ]),
            ],
        ]);
    }

    public function updateList(Request $request, int $id): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:120']);

        $list = EGroceryShoppingList::where('user_id', $request->user()->id)->findOrFail($id);
        $list->update(['name' => $request->name]);

        return response()->json(['success' => true, 'data' => $list]);
    }

    public function deleteList(Request $request, int $id): JsonResponse
    {
        $list = EGroceryShoppingList::where('user_id', $request->user()->id)->findOrFail($id);
        $list->items()->delete();
        $list->delete();

        return response()->json(['success' => true]);
    }

    public function addListItem(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|integer|exists:egrocery_products,id',
            'variant_id' => 'nullable|integer|exists:egrocery_product_variants,id',
            'qty'        => 'required|numeric|min:0.25|max:999',
        ]);

        $list = EGroceryShoppingList::where('user_id', $request->user()->id)->findOrFail($id);

        $existing = EGroceryShoppingListItem::where('list_id', $list->id)
            ->where('product_id', $request->product_id)
            ->when($request->variant_id, fn ($q) => $q->where('variant_id', $request->variant_id))
            ->first();

        if ($existing) {
            $existing->update(['qty' => $existing->qty + $request->qty]);
            $item = $existing;
        } else {
            $item = EGroceryShoppingListItem::create([
                'list_id'    => $list->id,
                'product_id' => $request->product_id,
                'variant_id' => $request->variant_id,
                'qty'        => $request->qty,
                'checked'    => false,
            ]);
        }

        return response()->json(['success' => true, 'data' => $item], 201);
    }

    public function updateListItem(Request $request, int $listId, int $itemId): JsonResponse
    {
        $request->validate([
            'qty'     => 'sometimes|numeric|min:0.25|max:999',
            'checked' => 'sometimes|boolean',
        ]);

        $list = EGroceryShoppingList::where('user_id', $request->user()->id)->findOrFail($listId);
        $item = EGroceryShoppingListItem::where('list_id', $list->id)->findOrFail($itemId);

        $item->update($request->only(['qty','checked']));

        return response()->json(['success' => true, 'data' => $item]);
    }

    public function deleteListItem(Request $request, int $listId, int $itemId): JsonResponse
    {
        $list = EGroceryShoppingList::where('user_id', $request->user()->id)->findOrFail($listId);
        EGroceryShoppingListItem::where('list_id', $list->id)->findOrFail($itemId)->delete();

        return response()->json(['success' => true]);
    }

    /**
     * POST /lists/{id}/add-all-to-cart
     * Returns a validated cart payload for the app to merge into its local cart.
     */
    public function listAddAllToCart(Request $request, int $id): JsonResponse
    {
        $list = EGroceryShoppingList::where('user_id', $request->user()->id)
            ->with(['items.variant'])
            ->findOrFail($id);

        $lines = $list->items
            ->filter(fn ($i) => $i->variant_id && !$i->checked)
            ->map(fn ($i) => ['variant_id' => $i->variant_id, 'qty' => $i->qty])
            ->values();

        return response()->json(['success' => true, 'data' => ['lines' => $lines]]);
    }

    // ── Cart Validate ─────────────────────────────────────────────────────────

    /**
     * POST /api/v1/egrocery/cart/validate
     * Re-prices every line, caps to stock, applies flash-deal limits, returns
     * corrected lines + totals + delivery fee + free-over progress.
     */
    public function cartValidate(Request $request): JsonResponse
    {
        $request->validate([
            'lines'         => 'required|array|min:1',
            'lines.*.variant_id' => 'required|integer',
            'lines.*.qty'   => 'required|numeric|min:0.01',
            'coupon'        => 'nullable|string',
            'address_id'    => 'nullable|integer',
        ]);

        $variantIds = collect($request->lines)->pluck('variant_id');
        $variants = EGroceryProductVariant::with('product:id,name,slug,images')
            ->whereIn('id', $variantIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        // Batch-price
        $prices = $this->pricing->resolveMany($variants);

        // Flash deal qty caps (total ordered across the cart)
        $flashDeals = EGroceryFlashDeal::whereIn('variant_id', $variantIds)
            ->whereHas('section', fn ($q) => $q->active())
            ->get()->keyBy('variant_id');

        $corrected = [];
        $warnings  = [];
        $subtotal  = 0;
        $changed   = false;

        foreach ($request->lines as $line) {
            $vid     = $line['variant_id'];
            $variant = $variants->get($vid);

            if (!$variant) {
                $warnings[] = ['variant_id' => $vid, 'code' => 'NOT_FOUND'];
                $changed = true;
                continue;
            }

            $qty = (float) $line['qty'];

            // Stock cap
            if ($variant->stock_qty <= 0) {
                $warnings[] = ['variant_id' => $vid, 'code' => 'OUT_OF_STOCK', 'available' => 0];
                $changed = true;
                continue;
            }

            if ($qty > $variant->stock_qty) {
                $qty = (float) $variant->stock_qty;
                $warnings[] = ['variant_id' => $vid, 'code' => 'STOCK_CHANGED', 'available' => $qty];
                $changed = true;
            }

            // Flash-deal qty cap
            $fd = $flashDeals->get($vid);
            if ($fd && $fd->qty_limit) {
                $qtyLeft = max(0, $fd->qty_limit - $fd->qty_sold);
                if ($qty > $qtyLeft) {
                    $qty = min($qty, $qtyLeft);
                    $warnings[] = ['variant_id' => $vid, 'code' => 'FLASH_DEAL_LIMIT', 'available' => $qty];
                    $changed = true;
                }
            }

            $price    = (float) ($prices[$vid]['effective_price'] ?? $variant->price);
            $lineTotal = round($qty * $price, 2);
            $subtotal += $lineTotal;

            $corrected[] = [
                'variant_id'    => $vid,
                'product_name'  => $variant->product?->name,
                'variant_label' => $variant->label,
                'image'         => $variant->product?->first_image,
                'qty'           => $qty,
                'unit_price'    => $price,
                'line_total'    => $lineTotal,
                'pricing'       => $prices[$vid] ?? [],
            ];
        }

        $subtotal = round($subtotal, 2);

        // Delivery fee for user's zone
        $deliveryFee = $this->resolveDeliveryFee($request);

        // Coupon validation
        $couponCode   = $request->input('coupon') ? strtoupper(trim($request->input('coupon'))) : null;
        $discount     = 0.0;
        $couponError  = null;
        $appliedCoupon = null;

        if ($couponCode) {
            $moduleId = DB::table('modules')->where('slug', 'egrocery')->value('id') ?: 10;
            $coupon = Coupon::where('code', $couponCode)
                ->where('module_id', $moduleId)
                ->where('is_active', true)
                ->first();

            if (!$coupon) {
                $couponError = 'INVALID_COUPON';
            } elseif ($coupon->starts_at && now()->lt($coupon->starts_at)) {
                $couponError = 'COUPON_NOT_STARTED';
            } elseif ($coupon->ends_at && now()->gt($coupon->ends_at)) {
                $couponError = 'COUPON_EXPIRED';
            } elseif ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
                $couponError = 'COUPON_LIMIT_REACHED';
            } elseif ($coupon->min_order_amount && $subtotal < $coupon->min_order_amount) {
                $couponError = 'COUPON_MIN_ORDER:' . $coupon->min_order_amount;
            } else {
                // Check per-user limit
                if ($coupon->usage_per_user) {
                    $userUsage = CouponUsage::where('coupon_id', $coupon->id)
                        ->where('user_id', $request->user()?->id)
                        ->count();
                    if ($userUsage >= $coupon->usage_per_user) {
                        $couponError = 'COUPON_USER_LIMIT_REACHED';
                    }
                }

                if (!$couponError) {
                    // Check category restriction
                    if ($coupon->category_ids) {
                        $allowedCatIds = (array)$coupon->category_ids;
                        $variantCatIds = EGroceryProductVariant::with('product:id,category_id')
                            ->whereIn('id', collect($corrected)->pluck('variant_id'))
                            ->get()
                            ->pluck('product.category_id')
                            ->unique()
                            ->values()
                            ->toArray();
                        if (empty(array_intersect($allowedCatIds, $variantCatIds))) {
                            $couponError = 'COUPON_CATEGORY_MISMATCH';
                        }
                    }
                }

                if (!$couponError) {
                    // Apply discount
                    if ($coupon->type === 'percentage') {
                        $discount = round($subtotal * ($coupon->value / 100), 2);
                        if ($coupon->max_discount) {
                            $discount = min($discount, (float)$coupon->max_discount);
                        }
                    } else {
                        $discount = min((float)$coupon->value, $subtotal);
                    }
                    $appliedCoupon = [
                        'code'     => $coupon->code,
                        'title'    => $coupon->title,
                        'type'     => $coupon->type,
                        'value'    => $coupon->value,
                        'discount' => $discount,
                    ];
                }
            }
        }

        $discount = round($discount, 2);

        // Free delivery after discount
        $zone = $this->findZoneForUser($request);
        if ($zone?->free_over && $subtotal >= $zone->free_over) {
            $deliveryFee = 0;
        }

        $total = round($subtotal + $deliveryFee - $discount, 2);

        // Free-delivery progress
        $freeOver = $this->resolveFreeOver($request);
        $freeProgress = $freeOver ? min(1.0, round($subtotal / $freeOver, 4)) : null;

        return response()->json([
            'success' => true,
            'data'    => [
                'lines'                  => $corrected,
                'changed'                => $changed,
                'warnings'               => $warnings,
                'subtotal'               => $subtotal,
                'delivery_fee'           => $deliveryFee,
                'discount'               => $discount,
                'coupon'                 => $appliedCoupon,
                'coupon_error'           => $couponError,
                'total'                  => $total,
                'free_over'              => $freeOver,
                'free_delivery_progress' => $freeProgress,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function resolveDeliveryFee(Request $request): float
    {
        $zone = $this->findZoneForUser($request);
        return $zone ? (float) $zone->delivery_fee : 2.00;
    }

    private function resolveFreeOver(Request $request): ?float
    {
        $zone = $this->findZoneForUser($request);
        return $zone?->free_over ? (float) $zone->free_over : null;
    }

    private function findZoneForUser(Request $request): ?EGroceryDeliveryZone
    {
        $districtId = $request->user()?->district_id;
        if (!$districtId) return EGroceryDeliveryZone::where('is_active', true)->first();

        return EGroceryDeliveryZone::where('is_active', true)->get()
            ->first(fn ($z) => in_array($districtId, (array)($z->district_ids ?? [])))
            ?? EGroceryDeliveryZone::where('is_active', true)->first();
    }

    private function transformProductCard(EGroceryProduct $product, array $prices): array
    {
        $variants = $product->activeVariants->map(fn ($v) => array_merge([
            'id'        => $v->id,
            'label'     => $v->label,
            'stock_qty' => (float) $v->stock_qty,
            'in_stock'  => $v->isInStock(),
            'is_default'=> (bool) $v->is_default,
        ], $prices[$v->id] ?? []));

        return [
            'id'      => $product->id,
            'name'    => $product->name,
            'slug'    => $product->slug,
            'image'   => $product->first_image,
            'variants'=> $variants,
        ];
    }
}
