<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Events\NewOrderForVendor;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with(['vendor:id,name,logo,cover_image', 'items.product:id,name,thumbnail,image'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest()
            ->get();

        // Normalize response for Flutter
        $result = $orders->map(function ($order) {
            return [
                'id'             => $order->id,
                'order_number'   => $order->order_number,
                'status'         => $order->status,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'subtotal'       => (float)$order->subtotal,
                'delivery_fee'   => (float)$order->delivery_fee,
                'discount'       => (float)($order->discount ?? $order->discount_amount ?? 0),
                'total_amount'   => (float)$order->total_amount,
                'module_slug'    => $order->module_slug,
                'note'           => $order->note ?? $order->notes,
                'placed_at'      => $order->placed_at,
                'created_at'     => $order->created_at,
                'vendor'         => $order->vendor ? [
                    'id'          => $order->vendor->id,
                    'name'        => $order->vendor->name,
                    'logo'        => $order->vendor->logo,
                    'cover_image' => $order->vendor->cover_image,
                ] : null,
                'items'          => $order->items->map(function($item) {
                    $meta = $item->meta ? (is_string($item->meta) ? json_decode($item->meta, true) : (array)$item->meta) : [];
                    return [
                        'id'           => $item->id,
                        'product_name' => $item->name ?? $item->product?->name ?? 'Item',
                        'quantity'     => (int)$item->quantity,
                        'price'        => (float)$item->price,
                        'total'        => (float)$item->total,
                        'variant_id'   => $item->variant_id ?? ($meta['variant_id'] ?? null),
                        'variant_name' => $meta['variant_name'] ?? null,
                        'variant_attrs'=> $meta['variant_attrs'] ?? null,
                        'product'      => $item->product ? [
                            'name'      => $item->product->name,
                            'image_url' => $item->product->thumbnail ?? $item->product->image,
                        ] : null,
                    ];
                }),
            ];
        });

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function show(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        try {
            $order->load(['vendor:id,name,logo,phone,address', 'items.product:id,name,thumbnail,image', 'statusHistory']);
        } catch (\Throwable) {
            $order->load(['vendor', 'items']);
        }

        // Build parcel_details for eParcel orders
        $parcelDetails = null;
        if ($order->module_slug === 'eparcel') {
            $noteRaw = $order->note ?? $order->notes;
            $note    = is_string($noteRaw) ? json_decode($noteRaw, true) : (array)$noteRaw;

            // Resolve district names
            $pickupDistrictId   = $note['pickup']['district_id'] ?? null;
            $deliveryAddr       = $order->delivery_address;
            if (is_string($deliveryAddr)) $deliveryAddr = json_decode($deliveryAddr, true);
            $deliveryDistrictId = $deliveryAddr['district_id'] ?? null;

            $pickupDistrict   = $pickupDistrictId   ? DB::table('districts')->find($pickupDistrictId)?->name   : null;
            $deliveryDistrict = $deliveryDistrictId ? DB::table('districts')->find($deliveryDistrictId)?->name : null;

            $parcelDetails = [
                'sender_name'       => $note['pickup']['name']    ?? $request->user()->name,
                'sender_phone'      => $note['pickup']['phone']   ?? $request->user()->phone,
                'pickup_district'   => $pickupDistrict,
                'recipient_name'    => $note['recipient']         ?? null,
                'recipient_phone'   => $note['recipient_phone']   ?? null,
                'delivery_district' => $deliveryDistrict,
                'description'       => $note['description']       ?? null,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'             => $order->id,
                'order_number'   => $order->order_number,
                'status'         => $order->status,
                'module_slug'    => $order->module_slug,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'subtotal'       => (float)$order->subtotal,
                'delivery_fee'   => (float)$order->delivery_fee,
                'discount'       => (float)($order->discount ?? $order->discount_amount ?? 0),
                'total_amount'   => (float)$order->total_amount,
                'note'           => $order->note ?? $order->notes,
                'placed_at'      => $order->placed_at,
                'created_at'     => $order->created_at,
                'vendor'         => $order->vendor,
                'parcel_details' => $parcelDetails,
                'items'          => $order->items->map(function($item) {
                    $meta = $item->meta ? (is_string($item->meta) ? json_decode($item->meta, true) : (array)$item->meta) : [];
                    return [
                        'id'           => $item->id,
                        'product_name' => $item->name ?? $item->product?->name ?? 'Item',
                        'quantity'     => (int)$item->quantity,
                        'price'        => (float)$item->price,
                        'total'        => (float)$item->total,
                        'variant_id'   => $item->variant_id ?? ($meta['variant_id'] ?? null),
                        'variant_name' => $meta['variant_name'] ?? null,
                        'variant_attrs'=> $meta['variant_attrs'] ?? null,
                        'product'      => $item->product ? [
                            'name'      => $item->product->name,
                            'image_url' => $item->product->thumbnail ?? $item->product->image,
                        ] : null,
                    ];
                }),
                'history'        => $order->statusHistory ?? [],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'vendor_id'               => 'required|exists:vendors,id',
            'delivery_address'        => 'required|array',
            'delivery_address.lat'    => 'required|numeric',
            'delivery_address.lng'    => 'required|numeric',
            'delivery_address.label'  => 'required|string',
            'payment_method'          => 'required|in:wallet,waafi,cod,mobile_pay',
            'items'                   => 'required|array|min:1',
            'items.*.product_id'      => 'required|exists:products,id',
            'items.*.quantity'        => 'required|integer|min:1',
            'items.*.variant_id'      => 'nullable|exists:product_variants,id',
            'items.*.addons'          => 'nullable|array',
            'coupon_code'             => 'nullable|string',
            'note'                    => 'nullable|string|max:300',
        ]);

        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user   = $request->user();
        $vendor = Vendor::findOrFail($request->vendor_id);

        if (!$vendor->is_active || !$vendor->is_approved) {
            return response()->json(['success' => false, 'message' => 'Vendor is not available'], 422);
        }

        // Calculate totals
        $subtotal = 0;
        $orderItems = [];

        foreach ($request->items as $item) {
            $product = Product::findOrFail($item['product_id']);
            if ($product->vendor_id !== $vendor->id) {
                return response()->json(['success' => false, 'message' => 'All items must be from the same vendor'], 422);
            }

            $price = $product->price;
            if (!empty($item['variant_id'])) {
                $variant = $product->variants()->findOrFail($item['variant_id']);
                $price = $variant->price;
            }

            $addonTotal = 0;
            if (!empty($item['addons'])) {
                $addonTotal = \App\Models\Addon::whereIn('id', $item['addons'])->sum('price');
            }

            $itemTotal = ($price + $addonTotal) * $item['quantity'];
            $subtotal += $itemTotal;

            $orderItems[] = [
                'product_id'  => $product->id,
                'variant_id'  => $item['variant_id'] ?? null,
                'name'        => $product->name,
                'price'       => $price,
                'quantity'    => $item['quantity'],
                'addon_ids'   => json_encode($item['addons'] ?? []),
                'addon_price' => $addonTotal,
                'total'       => $itemTotal,
            ];
        }

        // Delivery fee
        $deliveryFee = $vendor->delivery_fee ?? ($vendor->module->commission_value ? 2.00 : 0);

        // Commission
        $commissionRate = $vendor->commission_value ?? $vendor->module?->commission_value ?? 10;
        $commission = round($subtotal * $commissionRate / 100, 2);

        // Coupon
        $discount = 0;
        $couponId = null;
        if ($request->coupon_code) {
            $coupon = DB::table('coupons')
                ->where('code', $request->coupon_code)
                ->where('is_active', true)
                ->where(function($q) { $q->whereNull('expires_at')->orWhere('expires_at', '>', now()); })
                ->first();

            if ($coupon) {
                $usageCount = DB::table('coupon_usages')->where('coupon_id', $coupon->id)->where('user_id', $user->id)->count();
                if ($usageCount < ($coupon->max_uses_per_user ?? 1)) {
                    $discount = $coupon->discount_type === 'percentage'
                        ? round($subtotal * $coupon->discount_value / 100, 2)
                        : min($coupon->discount_value, $subtotal);
                    $couponId = $coupon->id;
                }
            }
        }

        $totalAmount = max(0, $subtotal + $deliveryFee - $discount);

        // Payment check
        if ($request->payment_method === 'wallet') {
            $wallet = $user->wallet;
            if (!$wallet || $wallet->balance < $totalAmount) {
                return response()->json(['success' => false, 'message' => 'Insufficient wallet balance'], 422);
            }
        }

        $order = DB::transaction(function () use (
            $request, $user, $vendor, $orderItems, $subtotal, $deliveryFee,
            $commission, $discount, $totalAmount, $couponId
        ) {
            $meta = [];
            if ($request->payment_method === 'mobile_pay' && $request->proof_token) {
                $meta['proof_token'] = $request->proof_token;
            }

            $order = Order::create([
                'order_number'     => 'ORD-' . strtoupper(Str::random(8)),
                'user_id'          => $user->id,
                'vendor_id'        => $vendor->id,
                'module_slug'      => $vendor->module_slug,
                'status'           => 'pending',
                'payment_method'   => $request->payment_method,
                'payment_status'   => in_array($request->payment_method, ['cod', 'mobile_pay']) ? 'pending' : 'paid',
                'delivery_address' => $request->delivery_address,
                'subtotal'         => $subtotal,
                'delivery_fee'     => $deliveryFee,
                'discount'         => $discount,
                'commission'       => $commission,
                'total_amount'     => $totalAmount,
                'note'             => $request->note,
                'placed_at'        => now(),
                'meta'             => $meta ?: null,
            ]);

            foreach ($orderItems as $item) {
                OrderItem::create(['order_id' => $order->id, ...$item]);
            }

            OrderStatusHistory::create([
                'order_id'  => $order->id,
                'status'    => 'pending',
                'note'      => 'Order placed by customer',
                'actor_id'  => $user->id,
                'actor_type'=> 'App\\Models\\User',
            ]);

            // Deduct wallet
            if ($request->payment_method === 'wallet') {
                $user->wallet()->lockForUpdate()->first()->decrement('balance', $totalAmount);
                DB::table('wallet_transactions')->insert([
                    'wallet_id'    => $user->wallet->id,
                    'type'         => 'debit',
                    'amount'       => $totalAmount,
                    'description'  => "Payment for Order #{$order->order_number}",
                    'reference_id' => $order->id,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            // Record coupon usage
            if ($couponId) {
                DB::table('coupon_usages')->insert([
                    'coupon_id'  => $couponId,
                    'user_id'    => $user->id,
                    'order_id'   => $order->id,
                    'created_at' => now(),
                ]);
            }

            // Commission record — vendor earning = subtotal - commission
            $commissionRate = $vendor->commission_value ?? $vendor->module?->commission_value ?? 10;
            DB::table('commissions')->insert([
                'order_id'         => $order->id,
                'vendor_id'        => $vendor->id,
                'module_id'        => $vendor->module_id,
                'commission_type'  => 'percentage',
                'commission_rate'  => $commissionRate,
                'order_amount'     => $subtotal,
                'commission_amount'=> $commission,
                'vendor_earning'   => round($subtotal - $commission, 2),
                'status'           => 'pending',
                'created_at'       => now(),
            ]);

            return $order;
        });

        // Notify vendor — realtime + FCM push
        try {
            $order->loadMissing(['items', 'user', 'vendor']);
            event(new NewOrderForVendor($order));

            // FCM push to vendor's user
            $vendorUser = $order->vendor?->user;
            if ($vendorUser?->fcm_token) {
                $itemCount   = $order->items->count();
                $customerName = $order->user?->name ?? 'Customer';
                \App\Services\FcmService::sendToToken(
                    $vendorUser->fcm_token,
                    '🛎 New Order #' . $order->order_number,
                    "{$customerName} · {$itemCount} item" . ($itemCount > 1 ? 's' : '') . ' · $' . number_format($order->total_amount, 2),
                    [
                        'type'         => 'vendor_new_order',
                        'order_id'     => (string) $order->id,
                        'order_number' => $order->order_number,
                        'total'        => (string) $order->total_amount,
                        'module'       => $order->module_slug ?? '',
                        'deep_link'    => '/orders',
                    ]
                );
            }
        } catch (\Throwable) {}

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully',
            'data'    => $order->load(['items', 'vendor']),
        ], 201);
    }

    public function cancel(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        if (!in_array($order->status, ['pending', 'confirmed'])) {
            return response()->json(['success' => false, 'message' => 'Order cannot be cancelled at this stage'], 422);
        }

        DB::transaction(function () use ($order, $request) {
            $order->update(['status' => 'cancelled']);

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => 'cancelled',
                'note'       => $request->reason ?? 'Cancelled by customer',
                'actor_id'   => $request->user()->id,
                'actor_type' => 'App\\Models\\User',
            ]);

            // Refund wallet if paid via wallet
            if ($order->payment_method === 'wallet' && $order->payment_status === 'paid') {
                $wallet = $request->user()->wallet;
                $wallet->increment('balance', $order->total_amount);

                DB::table('wallet_transactions')->insert([
                    'wallet_id'   => $wallet->id,
                    'type'        => 'credit',
                    'amount'      => $order->total_amount,
                    'description' => "Refund for cancelled Order #{$order->order_number}",
                    'reference_id'=> $order->id,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                $order->update(['payment_status' => 'refunded']);
            }
        });

        return response()->json(['success' => true, 'message' => 'Order cancelled successfully']);
    }

    public function tracking(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $tracking = $order->tracking()->latest()->first();
        $history  = $order->statusHistory()->with('actor')->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'status'          => $order->status,
                'current_location'=> $tracking ? ['lat' => $tracking->lat, 'lng' => $tracking->lng] : null,
                'history'         => $history,
                'deliveryman'     => $order->deliveryman?->only('id', 'name', 'phone', 'vehicle_type'),
            ],
        ]);
    }
}
