<?php

namespace App\Http\Controllers\Api\EGrocery;

use App\Http\Controllers\Controller;
use App\Models\EGrocery\{
    EGroceryOrder, EGroceryOrderItem, EGroceryProductVariant,
    EGroceryDeliverySlot, EGroceryDeliveryZone, EGroceryFlashDeal, EGroceryReview
};
use App\Models\{Coupon, CouponUsage, Order, Wallet};
use App\Services\EGrocery\{PricingService, StockService};
use App\Services\FcmService;
use App\Services\RealtimeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EGroceryOrderController extends Controller
{
    public function __construct(
        private PricingService $pricing,
        private StockService   $stock,
    ) {}

    // ── Delivery Slots ────────────────────────────────────────────────────────

    /**
     * GET /api/v1/egrocery/checkout/slots
     * Next 3 days of available slots (capacity-aware).
     */
    public function slots(Request $request): JsonResponse
    {
        $slots = EGroceryDeliverySlot::where('is_active', true)->get();

        $result = [];
        for ($d = 0; $d < 3; $d++) {
            $date = now()->addDays($d)->startOfDay();
            $dayName = strtolower($date->format('l'));

            $daySlots = $slots->filter(fn ($s) => $s->day_offset == $d || strtolower($s->label ?? '') === $dayName);

            // Fallback: use slots that match day_of_week if the model has that field
            if ($daySlots->isEmpty()) {
                $daySlots = $slots; // show all slots for each day (calendar-style)
            }

            foreach ($daySlots as $slot) {
                $booked = EGroceryOrder::where('delivery_slot_id', $slot->id)
                    ->whereDate('scheduled_date', $date->format('Y-m-d'))
                    ->whereNotIn('status', ['cancelled','refunded'])
                    ->count();

                $result[] = [
                    'slot_id'    => $slot->id,
                    'label'      => $slot->label ?? ($slot->start_time . ' – ' . $slot->end_time),
                    'start_time' => $slot->start_time,
                    'end_time'   => $slot->end_time,
                    'date'       => $date->format('Y-m-d'),
                    'capacity'   => $slot->capacity,
                    'booked'     => $booked,
                    'available'  => max(0, $slot->capacity - $booked),
                    'is_full'    => $booked >= $slot->capacity,
                ];
            }
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    // ── Checkout ──────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/egrocery/orders
     */
    public function checkout(Request $request): JsonResponse
    {
        \Log::info('[EGrocery checkout] input', $request->all());
        $request->validate([
            'address_id'        => 'nullable|integer',
            'slot_id'           => 'nullable|integer|exists:egrocery_delivery_slots,id',
            'scheduled_date'    => 'nullable|date',
            'payment_method'    => 'required|in:cod,cash,wallet,evc,mobile_pay,waafi_pay',
            'substitution_pref' => 'nullable|in:call_me,best_match,refund',
            'note'              => 'nullable|string|max:500',
            'lines'             => 'required|array|min:1',
            'lines.*.variant_id'=> 'required|integer',
            'lines.*.qty'       => 'required|numeric|min:0.01',
            'coupon'            => 'nullable|string',
        ]);

        $user = $request->user();

        try {
            $order = DB::transaction(function () use ($request, $user) {

                $variantIds = collect($request->lines)->pluck('variant_id');
                $variants = EGroceryProductVariant::with('product:id,name')
                    ->whereIn('id', $variantIds)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                // Validate: all variants found
                foreach ($request->lines as $line) {
                    if (!$variants->has($line['variant_id'])) {
                        throw new \DomainException('VARIANT_NOT_FOUND:' . $line['variant_id']);
                    }
                }

                // Check slot capacity
                if ($request->slot_id) {
                    $slot   = EGroceryDeliverySlot::find($request->slot_id);
                    $booked = EGroceryOrder::where('delivery_slot_id', $request->slot_id)
                        ->when($request->scheduled_date, fn ($q) => $q->whereDate('scheduled_date', $request->scheduled_date))
                        ->whereNotIn('status', ['cancelled','refunded'])
                        ->count();
                    if ($booked >= $slot->capacity) {
                        throw new \DomainException('SLOT_FULL');
                    }
                }

                // Batch-price
                $prices = $this->pricing->resolveMany($variants);

                // Flash deal qty caps
                $flashDeals = EGroceryFlashDeal::whereIn('variant_id', $variantIds)
                    ->whereHas('section', fn ($q) => $q->active())
                    ->lockForUpdate()
                    ->get()->keyBy('variant_id');

                // Build line items — re-validate price + stock
                $stockErrors  = [];
                $priceErrors  = [];
                $itemsData    = [];
                $subtotal     = 0.0;

                foreach ($request->lines as $line) {
                    $vid     = $line['variant_id'];
                    $variant = $variants[$vid];
                    $qty     = (float) $line['qty'];

                    // Stock
                    if ($variant->stock_qty < $qty) {
                        $stockErrors[] = ['variant_id' => $vid, 'available' => (float)$variant->stock_qty];
                    }

                    // Flash-deal limit
                    $fd = $flashDeals->get($vid);
                    if ($fd && $fd->qty_limit) {
                        $qtyLeft = max(0, $fd->qty_limit - $fd->qty_sold);
                        if ($qty > $qtyLeft) {
                            $stockErrors[] = ['variant_id' => $vid, 'available' => $qtyLeft, 'reason' => 'flash_deal_limit'];
                        }
                    }

                    $serverPrice = (float) ($prices[$vid]['effective_price'] ?? $variant->price);
                    $clientPrice = (float) ($line['unit_price'] ?? $serverPrice);

                    if (abs($serverPrice - $clientPrice) > 0.01) {
                        $priceErrors[] = ['variant_id' => $vid, 'server_price' => $serverPrice, 'client_price' => $clientPrice];
                    }

                    $lineTotal  = round($qty * $serverPrice, 2);
                    $subtotal  += $lineTotal;

                    $itemsData[] = [
                        'variant_id'          => $vid,
                        'name_snapshot'       => $variant->product?->name,
                        'unit_label_snapshot' => $variant->label,
                        'unit_price_snapshot' => $serverPrice,
                        'qty'                 => $qty,
                        'line_total'          => $lineTotal,
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ];
                }

                if (!empty($stockErrors)) {
                    throw new \DomainException('STOCK_CHANGED:' . json_encode($stockErrors));
                }
                if (!empty($priceErrors)) {
                    throw new \DomainException('PRICE_CHANGED:' . json_encode($priceErrors));
                }

                // Delivery fee — eParcel pricing: Hamarweyne (id=4) → user's district
                $deliveryFee = $this->egDeliveryFee($user->district_id ?? null);
                $minOrder    = 0; // No minimum order — managed by admin if needed later

                if ($subtotal < $minOrder) {
                    throw new \DomainException('MIN_ORDER_NOT_MET:' . $minOrder);
                }

                // No free-delivery threshold with eParcel-based pricing

                // ── Coupon ────────────────────────────────────────────────
                $couponDiscount = 0.0;
                $appliedCoupon  = null;
                $couponCode     = $request->input('coupon') ? strtoupper(trim($request->input('coupon'))) : null;

                if ($couponCode) {
                    $moduleId = DB::table('modules')->where('slug', 'egrocery')->value('id') ?: 10;
                    $coupon = Coupon::where('code', $couponCode)
                        ->where('module_id', $moduleId)
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();

                    if ($coupon
                        && (!$coupon->starts_at || now()->gte($coupon->starts_at))
                        && (!$coupon->ends_at   || now()->lte($coupon->ends_at))
                        && (!$coupon->usage_limit || $coupon->used_count < $coupon->usage_limit)
                        && (!$coupon->min_order_amount || $subtotal >= $coupon->min_order_amount)
                    ) {
                        $userUsageCount = $coupon->usage_per_user
                            ? CouponUsage::where('coupon_id', $coupon->id)->where('user_id', $user->id)->count()
                            : 0;

                        if (!$coupon->usage_per_user || $userUsageCount < $coupon->usage_per_user) {
                            if ($coupon->type === 'percentage') {
                                $couponDiscount = round($subtotal * ($coupon->value / 100), 2);
                                if ($coupon->max_discount) {
                                    $couponDiscount = min($couponDiscount, (float)$coupon->max_discount);
                                }
                            } else {
                                $couponDiscount = min((float)$coupon->value, $subtotal);
                            }
                            $appliedCoupon = $coupon;
                            $coupon->increment('used_count');
                        }
                    }
                }

                $discount = round($couponDiscount, 2);
                $total    = round($subtotal + $deliveryFee - $discount, 2);
                $orderNo  = 'EGR-' . strtoupper(Str::random(8));

                // ── Wallet payment: check balance BEFORE creating order ────────
                $walletUsed  = false;
                $walletObj   = null;
                if ($request->payment_method === 'wallet') {
                    $walletObj = Wallet::getOrCreateFor(\App\Models\User::class, $user->id);
                    if ((float) $walletObj->balance < $total) {
                        throw new \DomainException('INSUFFICIENT_WALLET_BALANCE:' . json_encode([
                            'balance'  => (float) $walletObj->balance,
                            'required' => $total,
                        ]));
                    }
                    $walletUsed = true;
                }

                // Create order
                $order = EGroceryOrder::create([
                    'order_no'          => $orderNo,
                    'user_id'           => $user->id,
                    'address_id'        => $request->address_id,
                    'status'            => 'pending',
                    'payment_method'    => $request->payment_method,
                    'payment_status'    => $walletUsed ? 'paid' : 'unpaid',
                    'subtotal'          => $subtotal,
                    'discount'          => $discount,
                    'delivery_fee'      => $deliveryFee,
                    'total'             => $total,
                    'delivery_slot_id'  => $request->slot_id,
                    'scheduled_date'    => $request->scheduled_date,
                    'substitution_pref' => $request->substitution_pref ?? 'best_match',
                    'customer_note'     => $request->note,
                ]);

                // Create items
                foreach ($itemsData as &$item) {
                    $item['order_id'] = $order->id;
                }
                EGroceryOrderItem::insert($itemsData);

                // Record coupon usage
                if ($appliedCoupon) {
                    CouponUsage::create([
                        'coupon_id'       => $appliedCoupon->id,
                        'user_id'         => $user->id,
                        'order_id'        => $order->id,
                        'discount_amount' => $discount,
                    ]);
                }

                // ── Deduct wallet after order row is committed ────────────────
                if ($walletUsed && $walletObj) {
                    $walletObj->decrement('balance', $total);
                    DB::table('wallet_transactions')->insert([
                        'wallet_id'   => $walletObj->id,
                        'type'        => 'debit',
                        'amount'      => $total,
                        'description' => 'eGrocery order #' . $orderNo,
                        'reference'   => $orderNo,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }

                // Mirror to main orders table (for admin dashboard / analytics / orders page)
                try {
                    // $deliveryFee already includes bonus from egDeliveryFee(); pass bonus_amount
                    // explicitly so the Order model hook does not double-add.
                    $mirrorBonus = \App\Services\DeliveryBonusService::getActiveBonusAmount();
                    Order::create([
                        'order_number'    => $orderNo,
                        'user_id'         => $user->id,
                        'module_slug'     => 'egrocery',
                        'module_id'       => 10, // modules.id for egrocery
                        'status'          => 'pending',
                        'payment_status'  => $walletUsed ? 'paid' : 'unpaid',
                        'payment_method'  => $request->payment_method ?? 'cash',
                        'subtotal'        => $subtotal,
                        'delivery_fee'    => $deliveryFee,
                        'bonus_amount'    => $mirrorBonus,  // already in delivery_fee — prevent hook double-add
                        'discount_amount' => $discount,
                        'total_amount'    => $total,
                        'delivery_address'=> [],
                        'placed_at'       => now(),
                        'meta'            => ['egrocery_order_id' => $order->id],
                    ]);
                } catch (\Throwable) {
                    // Mirror failure must never block order creation
                }

                // Decrement stock
                foreach ($request->lines as $line) {
                    $this->stock->sale(
                        $variants[$line['variant_id']],
                        (float) $line['qty'],
                        $orderNo,
                        $user->id,
                    );
                }

                // Increment flash deal qty_sold
                foreach ($request->lines as $line) {
                    $fd = $flashDeals->get($line['variant_id']);
                    $fd?->increment('qty_sold', $line['qty']);
                }

                return $order;
            });

        } catch (\DomainException $e) {
            $msg = $e->getMessage();
            if (str_starts_with($msg, 'STOCK_CHANGED:')) {
                return response()->json(['success' => false, 'code' => 'STOCK_CHANGED', 'errors' => json_decode(substr($msg, 14), true)], 422);
            }
            if (str_starts_with($msg, 'PRICE_CHANGED:')) {
                return response()->json(['success' => false, 'code' => 'PRICE_CHANGED', 'errors' => json_decode(substr($msg, 14), true)], 422);
            }
            if ($msg === 'SLOT_FULL') {
                return response()->json(['success' => false, 'code' => 'SLOT_FULL'], 422);
            }
            if (str_starts_with($msg, 'MIN_ORDER_NOT_MET:')) {
                return response()->json(['success' => false, 'code' => 'MIN_ORDER_NOT_MET', 'min_order' => substr($msg, 18)], 422);
            }
            if (str_starts_with($msg, 'INSUFFICIENT_WALLET_BALANCE:')) {
                $data = json_decode(substr($msg, 28), true);
                return response()->json([
                    'success'  => false,
                    'code'     => 'INSUFFICIENT_WALLET_BALANCE',
                    'message'  => 'Insufficient ePay wallet balance. Please top up your wallet.',
                    'balance'  => $data['balance'] ?? 0,
                    'required' => $data['required'] ?? 0,
                ], 422);
            }
            return response()->json(['success' => false, 'message' => $msg], 422);
        }

        // Fire FCM + Reverb
        $fcmToken = $request->user()->fcm_token;
        if ($fcmToken) {
            FcmService::sendToToken($fcmToken, 'Order Placed ✅', "Your order {$order->order_no} has been placed.", [
                'type'     => 'egrocery_order',
                'order_id' => (string) $order->id,
            ]);
        }
        RealtimeService::toUser($user->id, 'egrocery.order.placed', [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
        ]);

        return response()->json(['success' => true, 'data' => $this->transformOrder($order->load('items'))], 201);
    }

    // ── Order History ─────────────────────────────────────────────────────────

    public function orders(Request $request): JsonResponse
    {
        $orders = EGroceryOrder::where('user_id', $request->user()->id)
            ->with('items')
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $orders->getCollection()->map(fn ($o) => $this->transformOrder($o)),
            'meta'    => ['current_page' => $orders->currentPage(), 'last_page' => $orders->lastPage(), 'total' => $orders->total()],
        ]);
    }

    public function orderShow(Request $request, int $id): JsonResponse
    {
        $order = EGroceryOrder::where('user_id', $request->user()->id)
            ->with(['items', 'deliverySlot'])
            ->findOrFail($id);

        $data = $this->transformOrder($order);

        // Live tracking channel when out for delivery
        if ($order->status === 'out_for_delivery') {
            $data['tracking_channel'] = "egrocery.order.{$order->id}";
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function cancelOrder(Request $request, int $id): JsonResponse
    {
        $order = EGroceryOrder::where('user_id', $request->user()->id)->findOrFail($id);

        if (!in_array($order->status, ['pending', 'confirmed'])) {
            return response()->json(['success' => false, 'message' => 'Order cannot be cancelled at this stage'], 422);
        }

        $order->update(['status' => 'cancelled', 'cancelled_reason' => $request->reason ?? 'Cancelled by customer']);

        RealtimeService::toUser($order->user_id, 'egrocery.order.status', [
            'order_id' => $order->id,
            'status'   => 'cancelled',
        ]);

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request, int $id): JsonResponse
    {
        $order = EGroceryOrder::where('user_id', $request->user()->id)
            ->with('items.variant')
            ->findOrFail($id);

        $lines = $order->items->map(fn ($i) => [
            'variant_id'    => $i->variant_id,
            'qty'           => $i->qty,
            'product_name'  => $i->name_snapshot,
            'variant_label' => $i->unit_label_snapshot,
            'unit_price'    => $i->unit_price_snapshot,
        ]);

        return response()->json(['success' => true, 'data' => ['lines' => $lines]]);
    }

    // ── Substitutions ─────────────────────────────────────────────────────────

    public function respondToSubstitution(Request $request, int $orderId, int $itemId): JsonResponse
    {
        $request->validate(['action' => 'required|in:accept,reject']);

        $order = EGroceryOrder::where('user_id', $request->user()->id)->findOrFail($orderId);
        $item  = EGroceryOrderItem::where('order_id', $order->id)->findOrFail($itemId);

        if (!$item->substitution_variant_id) {
            return response()->json(['success' => false, 'message' => 'No substitution proposed'], 422);
        }

        $item->update(['substitution_status' => $request->action === 'accept' ? 'accepted' : 'rejected']);

        return response()->json(['success' => true]);
    }

    // ── Reviews ───────────────────────────────────────────────────────────────

    public function submitReview(Request $request, int $productId): JsonResponse
    {
        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        // Verify purchased and delivered
        $purchased = EGroceryOrderItem::whereHas('order', fn ($q) =>
            $q->where('user_id', $user->id)->where('status', 'delivered'))
            ->whereHas('variant', fn ($q) => $q->where('product_id', $productId))
            ->exists();

        if (!$purchased) {
            return response()->json(['success' => false, 'message' => 'You must purchase and receive this product first'], 403);
        }

        $review = EGroceryReview::updateOrCreate(
            ['user_id' => $user->id, 'product_id' => $productId],
            ['rating' => $request->rating, 'comment' => $request->comment, 'is_approved' => false]
        );

        // Recompute avg_rating
        $avg = EGroceryReview::where('product_id', $productId)->where('is_approved', true)->avg('rating');
        \App\Models\EGrocery\EGroceryProduct::where('id', $productId)->update(['avg_rating' => round($avg ?? 0, 2)]);

        return response()->json(['success' => true, 'data' => $review], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function transformOrder(EGroceryOrder $order): array
    {
        return [
            'id'                => $order->id,
            'order_no'          => $order->order_no,
            'status'            => $order->status,
            'payment_method'    => $order->payment_method,
            'payment_status'    => $order->payment_status,
            'subtotal'          => (float) $order->subtotal,
            'discount'          => (float) $order->discount,
            'delivery_fee'      => (float) $order->delivery_fee,
            'total'             => (float) $order->total,
            'substitution_pref' => $order->substitution_pref,
            'customer_note'     => $order->customer_note,
            'cancelled_reason'  => $order->cancelled_reason,
            'scheduled_date'    => $order->scheduled_date,
            'slot'              => $order->deliverySlot?->only(['id','label','start_time','end_time']),
            'confirmed_at'      => $order->confirmed_at?->toIso8601String(),
            'delivered_at'      => $order->delivered_at?->toIso8601String(),
            'created_at'        => $order->created_at->toIso8601String(),
            'items'             => $order->items->map(fn ($i) => [
                'id'                 => $i->id,
                'variant_id'         => $i->variant_id,
                'product_name'       => $i->name_snapshot,
                'variant_label'      => $i->unit_label_snapshot,
                'unit_price'         => (float) $i->unit_price_snapshot,
                'qty'                => (float) $i->qty,
                'line_total'         => (float) $i->line_total,
                'picked_qty'         => $i->picked_qty !== null ? (float) $i->picked_qty : null,
                'substitution_status'=> $i->substitution_status,
                'substitution_variant_id' => $i->substitution_variant_id,
            ]),
            // Reorder payload
            'reorder_payload' => $order->items->map(fn ($i) => [
                'variant_id' => $i->variant_id,
                'qty'        => (float) $i->qty,
            ]),
        ];
    }

    // eGrocery delivery fee: Hamarweyne (id=4) → user's district via eParcel pricing
    private const EG_BASE_DISTRICT = 4;
    private const EG_DEFAULT_FEE   = 2.00;

    private function egDeliveryFee(?int $districtId): float
    {
        if (!$districtId) {
            $base = self::EG_DEFAULT_FEE;
        } else {
            $row = DB::table('delivery_zone_pricing')
                ->where('module_id', 'eparcel')
                ->where('from_district_id', self::EG_BASE_DISTRICT)
                ->where('to_district_id', $districtId)
                ->where('is_active', true)
                ->first();
            $base = $row ? (float) $row->base_price : self::EG_DEFAULT_FEE;
        }
        // Add Peak Pay Bonus if active
        $bonus = \App\Services\DeliveryBonusService::getActiveBonusAmount();
        return round($base + $bonus, 2);
    }
}
