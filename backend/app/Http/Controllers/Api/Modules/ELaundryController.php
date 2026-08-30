<?php

namespace App\Http\Controllers\Api\Modules;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Services\LoyaltyService;

class ELaundryController extends Controller
{
    // GET /elaundry/items
    public function items()
    {
        $items = DB::table('laundry_items')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'name_so', 'normal_price', 'express_price', 'normal_days', 'express_hours', 'image']);

        $items = $items->map(fn($i) => array_merge((array)$i, ['image' => cdn_url($i->image)]));

        return response()->json(['success' => true, 'data' => $items]);
    }

    // POST /elaundry/estimate
    public function estimate(Request $request)
    {
        $v = Validator::make($request->all(), [
            'service_type'       => 'required|in:normal,express,mobile_pay',
            'items'              => 'required|array|min:1',
            'items.*.id'         => 'required|exists:laundry_items,id',
            'items.*.qty'        => 'required|integer|min:1',
            'pickup_district_id' => 'nullable|exists:districts,id',
            'self_pickup'        => 'nullable|boolean',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $isExpress  = $request->service_type === 'express';
        $priceField = $isExpress ? 'express_price' : 'normal_price';
        $itemIds    = array_column($request->items, 'id');
        $dbItems    = DB::table('laundry_items')->whereIn('id', $itemIds)->get()->keyBy('id');

        $lines = [];
        $total = 0;
        foreach ($request->items as $reqItem) {
            $dbItem = $dbItems[$reqItem['id']] ?? null;
            if (!$dbItem) continue;
            $price   = $dbItem->{$priceField};
            $sub     = $price * $reqItem['qty'];
            $total  += $sub;
            $lines[] = ['name' => $dbItem->name, 'qty' => $reqItem['qty'], 'price' => $price, 'subtotal' => $sub];
        }

        $firstItem = $dbItems->first();
        $etaLabel  = $isExpress
            ? ($firstItem ? $firstItem->express_hours . ' hours' : '24 hours')
            : ($firstItem ? $firstItem->normal_days   . ' days'  : '1-2 days');

        // Self-pickup → no delivery fee.
        // Otherwise: Hamarweyne (base) → customer district, × 2 (round trip: pickup + return).
        $selfPickup  = filter_var($request->input('self_pickup', false), FILTER_VALIDATE_BOOLEAN);
        if ($selfPickup) {
            $baseDelivery = 0.0;
        } else {
            $oneWay      = \App\Helpers\DeliveryPricing::forShopOrLaundry(
                $request->pickup_district_id ? (int)$request->pickup_district_id : null, 0
            );
            $baseDelivery = $oneWay * 2; // round trip
        }
        $bonusAmount       = $selfPickup ? 0.0 : \App\Services\DeliveryBonusService::getActiveBonusAmount();
        $deliveryFee       = round($baseDelivery + $bonusAmount, 2);
        $totalWithDelivery = round($total + $deliveryFee, 2);

        return response()->json([
            'success' => true,
            'data'    => [
                'service_type'  => $request->service_type,
                'delivery_days' => $etaLabel,
                'self_pickup'   => $selfPickup,
                'lines'         => $lines,
                'subtotal'      => round($total, 2),
                'delivery_fee'  => $deliveryFee,
                'bonus_amount'  => $bonusAmount,
                'total'         => $totalWithDelivery,
            ],
        ]);
    }

    // POST /elaundry/order (auth)
    public function createOrder(Request $request)
    {
        $selfPickup = filter_var($request->input('self_pickup', false), FILTER_VALIDATE_BOOLEAN);

        $v = Validator::make($request->all(), [
            'service_type'       => 'required|in:normal,express,mobile_pay',
            'items'              => 'required|array|min:1',
            'items.*.id'         => 'required|exists:laundry_items,id',
            'items.*.qty'        => 'required|integer|min:1',
            'pickup_district_id' => $selfPickup ? 'nullable' : 'required|exists:districts,id',
            'pickup_address'     => 'nullable|string',
            'delivery_address'   => 'nullable|string',
            'payment_method'     => 'required|in:wallet,cod,mobile_pay',
            'self_pickup'        => 'nullable|boolean',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user       = $request->user();
        $isExpress  = $request->service_type === 'express';
        $priceField = $isExpress ? 'express_price' : 'normal_price';
        $itemIds    = array_column($request->items, 'id');
        $dbItems    = DB::table('laundry_items')->whereIn('id', $itemIds)->get()->keyBy('id');

        $total = 0;
        $orderLines = [];
        foreach ($request->items as $reqItem) {
            $dbItem = $dbItems[$reqItem['id']] ?? null;
            if (!$dbItem) continue;
            $price = $dbItem->{$priceField};
            $sub   = $price * $reqItem['qty'];
            $total += $sub;
            $orderLines[] = ['name' => $dbItem->name, 'qty' => $reqItem['qty'], 'price' => $price, 'sub' => $sub];
        }

        // Points redeem
        $loyalty = LoyaltyService::processOrderRequest($request, $user->id, $total, 'elaundry');
        $total   = round(max(0, $total - $loyalty['points_discount']), 2);

        if ($request->payment_method === 'wallet') {
            if (!$user->wallet || $user->wallet->balance < $total) {
                return response()->json(['success' => false, 'message' => 'Insufficient wallet balance'], 422);
            }
        }

        $district = $selfPickup ? null : DB::table('districts')->find($request->pickup_district_id);

        // Self-pickup: no delivery fee.
        // Otherwise: Hamarweyne → customer district × 2 (round trip: pickup clothes + return).
        if ($selfPickup) {
            $baseFee     = 0.0;
            $bonusAmount = 0.0;
        } else {
            $oneWay      = \App\Helpers\DeliveryPricing::forShopOrLaundry((int)$request->pickup_district_id, 0);
            $baseFee     = $oneWay * 2;
            $bonusAmount = \App\Services\DeliveryBonusService::getActiveBonusAmount();
        }
        $deliveryFee       = round($baseFee + $bonusAmount, 2);
        $totalWithDelivery = $total + $deliveryFee;

        $order = DB::transaction(function () use ($request, $user, $total, $totalWithDelivery, $deliveryFee, $bonusAmount, $orderLines, $district, $isExpress, $dbItems, $loyalty, $selfPickup) {
            $order = Order::create([
                'order_number'    => 'LDR-' . strtoupper(Str::random(8)),
                'user_id'         => $user->id,
                'module_slug'     => 'elaundry',
                'status'          => 'pending',
                'payment_method'  => $request->payment_method,
                'payment_status'  => $request->payment_method === 'wallet' ? 'paid' : 'unpaid',
                'delivery_address'=> ['address' => $request->delivery_address ?? $request->pickup_address, 'district' => $district?->name ?? 'Self Pickup'],
                'subtotal'        => $total,
                'delivery_fee'    => $deliveryFee,
                'bonus_amount'    => $bonusAmount,  // already included in delivery_fee — prevent double-add by hook
                'total_amount'    => $totalWithDelivery,
                'points_used'     => $loyalty['points_used'],
                'points_discount' => $loyalty['points_discount'],
                'note'            => json_encode([
                    'service_type'   => $request->service_type,
                    'items'          => $orderLines,
                    'self_pickup'    => $selfPickup,
                    'pickup_address' => $request->pickup_address,
                    'district'       => $selfPickup ? 'Self Pickup' : $district?->name,
                    'eta'            => $isExpress
                        ? ($dbItems->first() ? $dbItems->first()->express_hours . ' hours' : '24 hours')
                        : ($dbItems->first() ? $dbItems->first()->normal_days   . ' days'  : '1-2 days'),
                ]),
                'placed_at' => now(),
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id, 'status' => 'pending',
                'note' => 'Laundry order placed', 'actor_id' => $user->id,
                'actor_type' => 'App\\Models\\User',
            ]);

            if ($request->payment_method === 'wallet') {
                $user->wallet->decrement('balance', $totalWithDelivery);
            }

            return $order;
        });

        // ── Push notification: order placed ──────────────────────────────
        try {
            if (!empty($user->fcm_token)) {
                \App\Services\FcmService::sendOrderUpdate(
                    $user->fcm_token,
                    $order->order_number,
                    'pending',
                    $order->id,
                    'elaundry',
                );
            }
        } catch (\Throwable) {}
        return response()->json([
            'success' => true,
            'message' => 'Laundry order placed!',
            'data'    => ['order_number' => $order->order_number, 'total' => $total],
        ], 201);
    }
}
