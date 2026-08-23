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
use App\Mail\NewOrderCustomerMail;
use App\Mail\NewOrderVendorMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
                'pickup_district'   => $pickupDistrict ?? $note['pickup_district_name'] ?? null,
                'recipient_name'    => $note['recipient']         ?? null,
                'recipient_phone'   => $note['recipient_phone']   ?? null,
                'delivery_district' => $deliveryDistrict ?? $note['delivery_district_name'] ?? null,
                'description'       => $note['description']       ?? null,
                'parcel_type'       => $note['parcel_type']       ?? null,
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

        // Realtime broadcast (separate try — failure must not kill FCM)
        try {
            $order->loadMissing(['items', 'user', 'vendor']);
            event(new NewOrderForVendor($order));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[ORDER] broadcast failed: ' . $e->getMessage());
        }

        if ($order->vendor_id) \App\Services\FcmService::notifyVendorNewOrder($order->vendor_id, $order, $order->module_slug ?? 'efood');

        // Admin alert is fired automatically via Order::created() model event

        // ── New order emails ─────────────────────────────────────────────
        try {
            $order->loadMissing(['user', 'vendor']);
            $moduleLabels = [
                'efood'     => 'eFood',     'egrocery'  => 'eGrocery',
                'eshop'     => 'eShop',     'eparcel'   => 'eParcel',
                'emoving'   => 'eMoving',   'erent'     => 'eRent',
                'elaundry'  => 'eLaundry',  'eticket'   => 'eTicket',
                'edata'     => 'eData',     'ehealth'   => 'eHealth',
                'wholesale' => 'Wholesale', 'eexchange' => 'eExchange',
            ];
            $moduleLabel  = $moduleLabels[$order->module_slug] ?? 'eSahlan';
            $total        = number_format((float) $order->total_amount, 2);
            $customerName = $order->user?->name ?? 'Customer';
            $orderNum     = $order->order_number;

            if ($order->user?->email) {
                Mail::to($order->user->email)->send(new NewOrderCustomerMail($customerName, $orderNum, $moduleLabel, $total));
            }

            $vendorEmail = $order->vendor?->email ?? $order->vendor?->user?->email;
            if ($vendorEmail) {
                Mail::to($vendorEmail)->send(new NewOrderVendorMail($order->vendor->name, $orderNum, $moduleLabel, $total, $customerName));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[NewOrderMail] ' . $e->getMessage());
        }

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
                $wallet = \App\Models\Wallet::getOrCreateFor('App\\Models\\User', $request->user()->id);
                $wallet->refund((float)$order->total_amount,
                    "Refund: cancelled Order #{$order->order_number}",
                    'App\\Models\\Order', $order->id);
                $order->update(['payment_status' => 'refunded']);
            }
        });

        return response()->json(['success' => true, 'message' => 'Order cancelled successfully']);
    }

    public function analytics(Request $request)
    {
        $userId = $request->user()->id;
        $now    = now();

        $base = DB::table('orders')->where('user_id', $userId)->whereNull('deleted_at');

        // Period counts
        $today    = (clone $base)->whereDate('placed_at', $now->toDateString())->count();
        $thisWeek = (clone $base)->whereBetween('placed_at', [$now->startOfWeek()->toDateTimeString(), $now->copy()->endOfWeek()->toDateTimeString()])->count();
        $now      = now(); // reset after startOfWeek mutates
        $thisMonth= (clone $base)->whereYear('placed_at', $now->year)->whereMonth('placed_at', $now->month)->count();
        $thisYear = (clone $base)->whereYear('placed_at', $now->year)->count();
        $total    = (clone $base)->count();

        // Total spent
        $totalSpent = (clone $base)->where('payment_status', 'paid')->sum('total_amount');

        // Module breakdown
        $modules = (clone $base)
            ->select('module_slug', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(total_amount) as spent'))
            ->groupBy('module_slug')
            ->orderByDesc('cnt')
            ->get()
            ->map(fn($r) => ['slug' => $r->module_slug ?? 'other', 'count' => $r->cnt, 'spent' => round($r->spent, 2)]);

        // Monthly trend (last 6 months)
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->subMonths($i);
            $cnt = (clone $base)->whereYear('placed_at', $m->year)->whereMonth('placed_at', $m->month)->count();
            $trend[] = ['month' => $m->format('M'), 'count' => $cnt];
        }

        // Status breakdown
        $statuses = (clone $base)
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->get()
            ->pluck('cnt', 'status');

        return response()->json(['success' => true, 'data' => [
            'total'       => $total,
            'today'       => $today,
            'this_week'   => $thisWeek,
            'this_month'  => $thisMonth,
            'this_year'   => $thisYear,
            'total_spent' => round($totalSpent, 2),
            'modules'     => $modules,
            'trend'       => $trend,
            'statuses'    => $statuses,
        ]]);
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
