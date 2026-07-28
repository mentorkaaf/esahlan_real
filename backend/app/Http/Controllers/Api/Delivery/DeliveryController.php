<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Deliveryman;
use App\Models\DeliverymanDocument;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Models\Wallet;
use App\Events\DeliveryLocationUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DeliveryController extends Controller
{
    private function dm(Request $request): ?Deliveryman
    {
        return Deliveryman::where('user_id', $request->user()->id)->first();
    }

    // ══════════════════════════════════════════════════════════════
    // AUTH
    // ══════════════════════════════════════════════════════════════

    public function register(Request $request)
    {
        $v = Validator::make($request->all(), [
            'name'         => 'required|string|max:100',
            'phone'        => 'required|string|unique:users,phone',
            'password'     => 'required|string|min:4',
            'driver_type'  => 'required|in:normal,truck',
            'vehicle_type' => 'required|in:motorcycle,car,bicycle,truck,bajaj,van,pickup',
            'plate_number' => 'nullable|string|max:50',
            'national_id'  => 'required|file|max:5120',
            'driver_license'=> 'required|file|max:5120',
            'vehicle_reg'  => 'required|file|max:5120',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $role = DB::table('roles')->where('slug', 'deliveryman')->first();

        $user = DB::transaction(function () use ($request, $role) {
            $user = User::create([
                'uuid'          => (string) Str::uuid(),
                'name'          => $request->name,
                'phone'         => $request->phone,
                'password'      => Hash::make($request->password),
                'wallet_pin'    => Hash::make($request->password),
                'role_id'       => $role?->id,
                'status'        => 'active',
                'referral_code' => strtoupper(Str::random(8)),
            ]);

            $dm = Deliveryman::create([
                'user_id'       => $user->id,
                'driver_type'   => $request->driver_type,
                'vehicle_type'  => $request->vehicle_type,
                'vehicle_plate' => $request->plate_number,
                'status'        => 'pending',
                'is_approved'   => false,
            ]);

            // Store required documents
            foreach (['national_id', 'driver_license', 'vehicle_reg'] as $docType) {
                if ($request->hasFile($docType)) {
                    $path = $request->file($docType)->store('deliveryman-docs', 'public');
                    DeliverymanDocument::create([
                        'deliveryman_id' => $dm->id,
                        'type'           => $docType,
                        'file_path'      => $path,
                        'status'         => 'pending',
                    ]);
                }
            }

            Wallet::getOrCreateFor('App\\Models\\User', $user->id);

            return $user;
        });

        $token = $user->createToken('driver-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration submitted. Awaiting admin approval.',
            'data'    => ['user' => $user, 'token' => $token],
        ], 201);
    }

    public function login(Request $request)
    {
        $v = Validator::make($request->all(), [
            'phone'    => 'required|string',
            'password' => 'required|string',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user = User::where('phone', $request->phone)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }

        $dm = Deliveryman::where('user_id', $user->id)->first();
        if (!$dm) return response()->json(['success' => false, 'message' => 'Not a driver account'], 403);

        if ($user->status === 'banned') {
            return response()->json(['success' => false, 'message' => 'Your account has been suspended'], 403);
        }

        $token = $user->createToken('driver-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => [
                'user'        => $user->only(['id', 'name', 'phone', 'email', 'avatar', 'status']),
                'deliveryman' => $dm,
                'token'       => $token,
                'is_approved' => (bool) $dm->is_approved,
                'driver_type' => $dm->driver_type ?? 'normal',
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // DASHBOARD
    // ══════════════════════════════════════════════════════════════

    public function dashboard(Request $request)
    {
        $dm = $this->dm($request);
        if (!$dm) return response()->json(['success' => false, 'message' => 'Profile not found'], 404);

        $todayOrders   = Order::where('deliveryman_id', $dm->id)->whereDate('created_at', today())->count();
        $todayEarnings = DB::table('deliveryman_earnings')
            ->where('deliveryman_id', $dm->id)->whereDate('created_at', today())->sum('amount');
        $totalEarnings = DB::table('deliveryman_earnings')
            ->where('deliveryman_id', $dm->id)->sum('amount');
        $weeklyEarnings = DB::table('deliveryman_earnings')
            ->where('deliveryman_id', $dm->id)->where('created_at', '>=', now()->startOfWeek())->sum('amount');

        $activeOrder = Order::where('deliveryman_id', $dm->id)
            ->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery'])
            ->with(['vendor:id,name,address,latitude,longitude,phone', 'user:id,name,phone'])
            ->first();

        $completedToday = Order::where('deliveryman_id', $dm->id)
            ->whereDate('created_at', today())->where('status', 'delivered')->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'status'          => $dm->status,
                'driver_type'     => $dm->driver_type ?? 'normal',
                'is_online'       => (bool) $dm->is_online,
                'is_approved'     => (bool) $dm->is_approved,
                'today_orders'    => $todayOrders,
                'completed_today' => $completedToday,
                'today_earnings'  => round((float) $todayEarnings, 2),
                'weekly_earnings' => round((float) $weeklyEarnings, 2),
                'total_earnings'  => round((float) $totalEarnings, 2),
                'active_order'    => $activeOrder,
                'rating'          => round($dm->rating ?? 5.0, 1),
                'total_deliveries'=> $dm->total_deliveries,
                'wallet_balance'  => (float) (Wallet::getOrCreateFor('App\\Models\\User', $request->user()->id)->balance ?? 0),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // ORDERS
    // ══════════════════════════════════════════════════════════════

    private const NORMAL_MODULES = ['efood', 'eshop', 'eparcel', 'egrocery', 'elaundry'];
    private const TRUCK_MODULES  = ['emoving'];

    public function availableOrders(Request $request)
    {
        $dm = $this->dm($request);
        if (!$dm || !$dm->is_approved) {
            return response()->json(['success' => false, 'message' => 'Not approved'], 403);
        }

        $allowedModules = $dm->driver_type === 'truck' ? self::TRUCK_MODULES : self::NORMAL_MODULES;

        $orders = Order::whereNull('deliveryman_id')
            ->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup'])
            ->whereIn('module_slug', $allowedModules)
            ->with(['vendor:id,name,address,latitude,longitude,phone,logo', 'user:id,name,phone'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn($o) => $this->formatOrder($o, $dm));

        return response()->json(['success' => true, 'data' => $orders]);
    }

    public function activeOrders(Request $request)
    {
        $dm = $this->dm($request);
        if (!$dm) return response()->json(['success' => false, 'message' => 'Profile not found'], 404);

        $orders = Order::where('deliveryman_id', $dm->id)
            ->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery'])
            ->with(['vendor:id,name,address,latitude,longitude,phone,logo', 'user:id,name,phone', 'items'])
            ->get()
            ->map(fn($o) => $this->formatOrder($o, $dm));

        return response()->json(['success' => true, 'data' => $orders]);
    }

    public function orderHistory(Request $request)
    {
        $dm = $this->dm($request);
        if (!$dm) return response()->json(['success' => false, 'message' => 'Profile not found'], 404);

        $orders = Order::where('deliveryman_id', $dm->id)
            ->whereIn('status', ['delivered', 'cancelled'])
            ->with(['vendor:id,name,logo', 'user:id,name'])
            ->latest()
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $orders]);
    }

    public function acceptOrder(Request $request, Order $order)
    {
        $dm = $this->dm($request);
        if (!$dm || !$dm->is_approved) return response()->json(['success' => false, 'message' => 'Not approved'], 403);

        if ($order->deliveryman_id) {
            return response()->json(['success' => false, 'message' => 'Order already assigned'], 422);
        }

        // Max orders limit (admin configurable)
        $maxOrders = (int) \App\Helpers\AppSettings::get('max_orders_per_driver', 5);
        $activeCount = Order::where('deliveryman_id', $dm->id)
            ->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery'])
            ->count();
        if ($activeCount >= $maxOrders) {
            return response()->json(['success' => false, 'message' => "Maximum {$maxOrders} active orders. Complete existing deliveries first."], 422);
        }

        DB::transaction(function () use ($order, $dm, $request) {
            $updateData = [
                'deliveryman_id' => $dm->id,
                'dispatched_at'  => now(),
            ];

            // Set delivery_fee from pricing if not already set
            if (($order->delivery_fee ?? 0) == 0) {
                $noteData = is_string($order->note) ? json_decode($order->note, true) : ($order->note ?? []);

                if ($order->module_slug === 'emoving' && $noteData) {
                    $fromDist = DB::table('districts')->where('name', $noteData['from_district'] ?? '')->first();
                    $toDist = DB::table('districts')->where('name', $noteData['to_district'] ?? '')->first();
                    if ($fromDist && $toDist) {
                        $mp = DB::table('moving_pricing')->where('from_district_id', $fromDist->id)->where('to_district_id', $toDist->id)->where('is_active', true)->first();
                        $updateData['delivery_fee'] = $mp ? (float) ($mp->distance_price ?? 20) : (float) ($noteData['distance_fee'] ?? 20);
                    } else {
                        $updateData['delivery_fee'] = (float) ($noteData['distance_fee'] ?? 20);
                    }
                } elseif ($order->module_slug === 'eparcel') {
                    $addr = is_string($order->delivery_address) ? json_decode($order->delivery_address, true) : ($order->delivery_address ?? []);
                    $pickupInfo = $noteData['pickup'] ?? [];
                    $fromId = $pickupInfo['district_id'] ?? null;
                    $toId = $addr['district_id'] ?? null;
                    if ($fromId && $toId) {
                        $zone = DB::table('delivery_zone_pricing')->where('from_district_id', $fromId)->where('to_district_id', $toId)->where('is_active', true)->first();
                        if ($zone) $updateData['delivery_fee'] = (float) $zone->base_price;
                    }
                }
            }

            $order->update($updateData);

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => $order->status,
                'note'       => 'Driver accepted the order',
                'changed_by' => $request->user()->id,
            ]);

            $dm->update(['status' => 'busy', 'is_available' => false]);
        });

        // Notify customer
        try {
            $order->load('user');
            if ($order->user?->fcm_token) {
                \App\Services\FcmService::sendOrderUpdate(
                    $order->user->fcm_token, $order->order_number, 'out_for_delivery', $order->id, $order->module_slug
                );
            }
        } catch (\Throwable) {}

        // Notify driver — order assigned confirmation
        try {
            $driverToken = $dm->fcm_token ?? $request->user()->fcm_token;
            if ($driverToken) {
                \App\Services\FcmService::sendDriverOrderUpdate(
                    $driverToken, $order->order_number, 'out_for_delivery', $order->id, $order->module_slug
                );
            }
        } catch (\Throwable) {}

        return response()->json(['success' => true, 'message' => 'Order accepted']);
    }

    public function rejectOrder(Request $request, Order $order)
    {
        return response()->json(['success' => true, 'message' => 'Order skipped']);
    }

    public function updateOrderStatus(Request $request, Order $order)
    {
        $dm = $this->dm($request);
        if (!$dm || $order->deliveryman_id !== $dm->id) {
            return response()->json(['success' => false, 'message' => 'Not your order'], 403);
        }

        $v = Validator::make($request->all(), [
            'status'       => 'required|in:out_for_delivery,delivered',
            'note'         => 'nullable|string',
            'delivery_photo'=> 'nullable|image|max:5120',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        DB::transaction(function () use ($order, $request, $dm) {
            $data = ['status' => $request->status];

            if ($request->status === 'out_for_delivery') {
                $data['picked_up_at'] = now();
            }

            if ($request->status === 'delivered') {
                $data['delivered_at'] = now();

                // Store delivery confirmation photo
                if ($request->hasFile('delivery_photo')) {
                    $photoPath = $request->file('delivery_photo')->store('delivery-photos', 'public');
                    $meta = $order->meta ?? [];
                    $meta['delivery_photo'] = $photoPath;
                    $data['meta'] = $meta;
                }

                // Delivery fee — split between driver and admin commission
                $fee = (float) ($order->delivery_fee ?? 0);
                $deliveryCommissionPct = (float) (DB::table('settings')->where('key', 'delivery_fee_commission_pct')->value('value') ?? 0);
                $adminDeliveryCommission = $fee > 0 ? round($fee * $deliveryCommissionPct / 100, 2) : 0;
                $driverEarning = max(0, $fee - $adminDeliveryCommission);

                if ($driverEarning > 0) {
                    $wallet = Wallet::getOrCreateFor('App\\Models\\User', $request->user()->id);
                    $wallet->credit($driverEarning, "Delivery: #{$order->order_number}", 'App\\Models\\Order', $order->id);
                }

                // Record earning (driver gets their share)
                DB::table('deliveryman_earnings')->insert([
                    'deliveryman_id' => $dm->id,
                    'order_id'       => $order->id,
                    'type'           => 'delivery_fee',
                    'amount'         => $driverEarning,
                    'note'           => "Order #{$order->order_number}" . ($adminDeliveryCommission > 0 ? " (fee commission: -{$adminDeliveryCommission})" : ''),
                    'created_at'     => now(),
                ]);

                // Settle commission record — use commission table as fallback if vendor_id not set on order
                $commissionRow = DB::table('commissions')
                    ->where('order_id', $order->id)
                    ->where('status', 'pending')
                    ->first();

                $effectiveVendorId = $order->vendor_id ?? $commissionRow?->vendor_id;

                if ($effectiveVendorId) {
                    $vendorEarning = $commissionRow
                        ? (float) $commissionRow->vendor_earning
                        : max(0, (float) $order->subtotal - (float) ($order->commission ?? 0));

                    if ($vendorEarning > 0) {
                        $vendorWallet = Wallet::getOrCreateFor('App\\Models\\Vendor', $effectiveVendorId);
                        $vendorWallet->credit($vendorEarning, "Order #{$order->order_number} earning", 'App\\Models\\Order', $order->id);
                    }
                }

                // Always settle any pending commission — record delivery fee commission for admin tracking
                DB::table('commissions')
                    ->where('order_id', $order->id)
                    ->where('status', 'pending')
                    ->update([
                        'status'                  => 'settled',
                        'settled_at'              => now(),
                        'delivery_fee'            => $fee,
                        'delivery_fee_commission' => $adminDeliveryCommission,
                    ]);

                $dm->update(['status' => 'available', 'is_available' => true]);
                $dm->increment('total_deliveries');
            }

            $order->update($data);

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => $request->status,
                'note'       => $request->note ?? 'Updated by driver',
                'changed_by' => $request->user()->id,
            ]);
        });

        // Notify customer
        try {
            $order->load('user');
            if ($order->user?->fcm_token) {
                \App\Services\FcmService::sendOrderUpdate(
                    $order->user->fcm_token, $order->order_number, $request->status, $order->id, $order->module_slug
                );
            }
        } catch (\Throwable) {}

        // Notify driver — delivery complete / status change
        try {
            $driverToken = $dm->fcm_token ?? $request->user()->fcm_token;
            if ($driverToken) {
                \App\Services\FcmService::sendDriverOrderUpdate(
                    $driverToken, $order->order_number, $request->status, $order->id, $order->module_slug
                );
            }
        } catch (\Throwable) {}

        return response()->json(['success' => true, 'message' => 'Status updated']);
    }

    // ══════════════════════════════════════════════════════════════
    // LOCATION
    // ══════════════════════════════════════════════════════════════

    public function updateLocation(Request $request)
    {
        $v = Validator::make($request->all(), [
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'order_id'  => 'nullable|exists:orders,id',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $dm = $this->dm($request);
        if (!$dm) return response()->json(['success' => false], 404);

        $dm->loadMissing('user:id,name');
        $dm->update([
            'latitude'        => $request->latitude,
            'longitude'       => $request->longitude,
            'last_location_at'=> now(),
        ]);

        if ($request->order_id) {
            $order = Order::find($request->order_id);
            if ($order && $order->deliveryman_id === $dm->id) {
                DB::table('order_tracking')->insert([
                    'order_id'       => $order->id,
                    'deliveryman_id' => $dm->id,
                    'latitude'       => $request->latitude,
                    'longitude'      => $request->longitude,
                    'created_at'     => now(),
                ]);
                // Broadcast to order channel for customer tracking
                event(new DeliveryLocationUpdated(
                    $order->id,
                    $request->latitude,
                    $request->longitude,
                    $order->status
                ));
            }
        }

        // Broadcast to admin drivers channel for live map
        broadcast(new \App\Events\DriverLocationUpdated(
            $dm->id,
            $dm->user_id,
            $dm->user?->name ?? 'Driver',
            $request->latitude,
            $request->longitude,
            $dm->status,
            $request->order_id,
        ))->toOthers();

        return response()->json(['success' => true]);
    }

    public function toggleStatus(Request $request)
    {
        $dm = $this->dm($request);
        if (!$dm) return response()->json(['success' => false], 404);

        $goingOnline = !$dm->is_online;
        $dm->update([
            'is_online'    => $goingOnline,
            'is_available' => $goingOnline,
            'status'       => $goingOnline ? 'available' : 'offline',
        ]);

        return response()->json([
            'success' => true,
            'data'    => ['is_online' => $goingOnline, 'status' => $dm->status],
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // EARNINGS
    // ══════════════════════════════════════════════════════════════

    public function earnings(Request $request)
    {
        $dm = $this->dm($request);
        if (!$dm) return response()->json(['success' => false], 404);

        $todayEarnings   = DB::table('deliveryman_earnings')->where('deliveryman_id', $dm->id)->whereDate('created_at', today())->sum('amount');
        $weeklyEarnings  = DB::table('deliveryman_earnings')->where('deliveryman_id', $dm->id)->where('created_at', '>=', now()->startOfWeek())->sum('amount');
        $monthlyEarnings = DB::table('deliveryman_earnings')->where('deliveryman_id', $dm->id)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('amount');
        $totalEarnings   = DB::table('deliveryman_earnings')->where('deliveryman_id', $dm->id)->sum('amount');

        $dailyChart = DB::table('deliveryman_earnings')
            ->where('deliveryman_id', $dm->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total, COUNT(*) as deliveries')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        $recentDeliveries = DB::table('deliveryman_earnings')
            ->where('deliveryman_earnings.deliveryman_id', $dm->id)
            ->leftJoin('orders', 'orders.id', '=', 'deliveryman_earnings.order_id')
            ->leftJoin('commissions', 'commissions.order_id', '=', 'deliveryman_earnings.order_id')
            ->select(
                'deliveryman_earnings.*',
                'orders.order_number',
                'orders.module_slug',
                'orders.delivery_fee as original_delivery_fee',
                'commissions.delivery_fee_commission'
            )
            ->orderByDesc('deliveryman_earnings.created_at')
            ->limit(20)
            ->get();

        // Total platform commission deducted from this driver's delivery fees
        $totalPlatformCommission = DB::table('commissions')
            ->join('deliveryman_earnings', 'commissions.order_id', '=', 'deliveryman_earnings.order_id')
            ->where('deliveryman_earnings.deliveryman_id', $dm->id)
            ->sum('commissions.delivery_fee_commission');

        return response()->json([
            'success' => true,
            'data'    => [
                'today'                    => round((float) $todayEarnings, 2),
                'weekly'                   => round((float) $weeklyEarnings, 2),
                'monthly'                  => round((float) $monthlyEarnings, 2),
                'total'                    => round((float) $totalEarnings, 2),
                'total_platform_commission'=> round((float) $totalPlatformCommission, 2),
                'total_orders'             => $dm->total_deliveries,
                'rating'                   => round($dm->rating ?? 5.0, 1),
                'daily_chart'              => $dailyChart,
                'recent'                   => $recentDeliveries,
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // PROFILE
    // ══════════════════════════════════════════════════════════════

    public function profile(Request $request)
    {
        $dm = $this->dm($request);
        if (!$dm) return response()->json(['success' => false], 404);

        $dm->load(['user:id,name,phone,email,avatar,status', 'documents', 'district:id,name']);

        return response()->json([
            'success' => true,
            'data'    => $dm,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $dm = $this->dm($request);
        if (!$dm) return response()->json(['success' => false], 404);

        $v = Validator::make($request->all(), [
            'name'          => 'nullable|string|max:100',
            'vehicle_type'  => 'nullable|in:motorcycle,car,bicycle,truck,bajaj,van,pickup',
            'vehicle_plate' => 'nullable|string|max:50',
            'vehicle_model' => 'nullable|string|max:100',
            'avatar'        => 'nullable|image|max:2048',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        if ($request->filled('name')) {
            $request->user()->update(['name' => $request->name]);
        }

        $dmUpdate = [];
        if ($request->filled('vehicle_type'))  $dmUpdate['vehicle_type']  = $request->vehicle_type;
        if ($request->filled('vehicle_plate')) $dmUpdate['vehicle_plate'] = $request->vehicle_plate;
        if ($request->filled('vehicle_model')) $dmUpdate['vehicle_model'] = $request->vehicle_model;

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $request->user()->update(['avatar' => $path]);
        }

        if (!empty($dmUpdate)) $dm->update($dmUpdate);

        return response()->json(['success' => true, 'message' => 'Profile updated']);
    }

    public function updateFcmToken(Request $request)
    {
        $request->validate(['token' => 'required|string']);
        $dm = $this->dm($request);
        if ($dm) $dm->update(['fcm_token' => $request->token]);
        $request->user()->update(['fcm_token' => $request->token]);
        return response()->json(['success' => true]);
    }

    // ══════════════════════════════════════════════════════════════
    // WALLET
    // ══════════════════════════════════════════════════════════════

    public function wallet(Request $request)
    {
        $wallet = Wallet::getOrCreateFor('App\\Models\\User', $request->user()->id);

        return response()->json([
            'success' => true,
            'data'    => [
                'balance'          => (float) $wallet->balance,
                'total_earned'     => (float) $wallet->total_earned,
                'total_withdrawn'  => (float) $wallet->total_withdrawn,
                'pending_withdrawal'=> (float) DB::table('withdrawal_requests')
                    ->where('owner_type', 'App\\Models\\User')
                    ->where('owner_id', $request->user()->id)
                    ->where('status', 'pending')
                    ->sum('amount'),
            ],
        ]);
    }

    public function walletTransactions(Request $request)
    {
        $wallet = Wallet::getOrCreateFor('App\\Models\\User', $request->user()->id);

        $txns = DB::table('transactions')
            ->where('wallet_id', $wallet->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $txns]);
    }

    public function withdrawRequest(Request $request)
    {
        $v = Validator::make($request->all(), [
            'amount'         => 'required|numeric|min:1',
            'payment_method' => 'required|in:waafi,evc,bank',
            'account_number' => 'required|string',
            'account_name'   => 'required|string',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $wallet = Wallet::getOrCreateFor('App\\Models\\User', $request->user()->id);
        if ($wallet->balance < $request->amount) {
            return response()->json(['success' => false, 'message' => 'Insufficient balance'], 422);
        }

        DB::transaction(function () use ($wallet, $request) {
            $wallet->debit((float) $request->amount, 'Withdrawal request - pending');
            DB::table('withdrawal_requests')->insert([
                'wallet_id'      => $wallet->id,
                'owner_type'     => 'App\\Models\\User',
                'owner_id'       => $request->user()->id,
                'amount'         => $request->amount,
                'method'         => $request->payment_method,
                'payment_method' => $request->payment_method,
                'account_number' => $request->account_number,
                'account_name'   => $request->account_name,
                'status'         => 'pending',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        });

        return response()->json(['success' => true, 'message' => 'Withdrawal request submitted']);
    }

    // ══════════════════════════════════════════════════════════════
    // DOCUMENTS
    // ══════════════════════════════════════════════════════════════

    public function uploadDocument(Request $request)
    {
        $v = Validator::make($request->all(), [
            'type' => 'required|in:national_id,driver_license,vehicle_registration,profile_photo,selfie',
            'file' => 'required|file|max:5120',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $dm = $this->dm($request);
        if (!$dm) return response()->json(['success' => false], 404);

        $path = $request->file('file')->store('deliveryman-docs', 'public');

        DeliverymanDocument::updateOrCreate(
            ['deliveryman_id' => $dm->id, 'type' => $request->type],
            ['file_path' => $path, 'status' => 'pending']
        );

        return response()->json(['success' => true, 'message' => 'Document uploaded']);
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════

    private function formatOrder(Order $order, Deliveryman $dm): array
    {
        $addr = $order->delivery_address;
        if (is_string($addr)) $addr = json_decode($addr, true);

        // Resolve districts for pickup and delivery
        $pickupDistrict = $order->vendor?->district;
        $deliveryDistrictName = $addr['district'] ?? $addr['city'] ?? null;
        $deliveryDistrict = $deliveryDistrictName
            ? DB::table('districts')->where('name', $deliveryDistrictName)->first()
            : ($order->user?->district_id ? DB::table('districts')->find($order->user->district_id) : null);

        // Pickup coordinates (vendor or sender)
        $pickupLat = (float) ($order->vendor?->latitude ?? 0);
        $pickupLng = (float) ($order->vendor?->longitude ?? 0);

        // Delivery coordinates (customer GPS → district center)
        $deliveryLat = (float) ($order->user?->latitude ?? $deliveryDistrict?->latitude ?? 0);
        $deliveryLng = (float) ($order->user?->longitude ?? $deliveryDistrict?->longitude ?? 0);

        // If no pickup coords, use pickup district center
        if ($pickupLat == 0 && $pickupDistrict) {
            $pickupLat = (float) ($pickupDistrict->latitude ?? 0);
            $pickupLng = (float) ($pickupDistrict->longitude ?? 0);
        }

        // Calculate distance between pickup and delivery
        $distance = null;
        if ($pickupLat != 0 && $deliveryLat != 0) {
            $distance = round($this->haversine($pickupLat, $pickupLng, $deliveryLat, $deliveryLng), 1);
        }

        // Estimate time (avg 20km/h in city)
        $estimatedMinutes = $distance ? (int) round($distance * 3) : null;

        // Zone pricing lookup
        $zoneFee = (float) ($order->delivery_fee ?? 0);
        if ($zoneFee == 0 && $pickupDistrict && $deliveryDistrict) {
            $zone = DB::table('delivery_zone_pricing')
                ->where('from_district_id', $pickupDistrict->id ?? $order->vendor?->district_id)
                ->where('to_district_id', $deliveryDistrict->id)
                ->where('is_active', true)->first();
            if ($zone) $zoneFee = (float) $zone->base_price;
        }

        // Parse note/meta for module-specific data
        $noteData = null;
        $rawNote = $order->note ?? $order->notes;
        if ($rawNote) {
            if (is_string($rawNote)) { try { $noteData = json_decode($rawNote, true); } catch (\Throwable) {} }
            elseif (is_array($rawNote)) { $noteData = $rawNote; }
        }

        $result = [
            'id'              => $order->id,
            'order_number'    => $order->order_number,
            'status'          => $order->status,
            'module_slug'     => $order->module_slug,
            'total_amount'    => (float) $order->total_amount,
            'subtotal'        => (float) ($order->subtotal ?? 0),
            'delivery_fee'    => $zoneFee,
            'items_count'     => $order->items?->count() ?? 0,
            'distance_km'     => $distance,
            'estimated_minutes'=> $estimatedMinutes,

            // Pickup info
            'pickup' => [
                'name'     => $order->vendor?->name ?? ($noteData['sender_name'] ?? 'Pickup'),
                'phone'    => $order->vendor?->phone ?? ($noteData['sender_phone'] ?? null),
                'address'  => $order->vendor?->address ?? ($noteData['pickup_address'] ?? $noteData['from_address'] ?? null),
                'district' => $pickupDistrict?->name ?? ($noteData['district'] ?? null),
                'lat'      => $pickupLat,
                'lng'      => $pickupLng,
            ],

            // Delivery info
            'delivery' => [
                'name'     => $order->user?->name ?? ($noteData['receiver_name'] ?? 'Customer'),
                'phone'    => $order->user?->phone ?? ($noteData['receiver_phone'] ?? null),
                'address'  => $addr['address'] ?? ($noteData['receiver_address'] ?? $noteData['to_address'] ?? null),
                'district' => $deliveryDistrict?->name ?? $deliveryDistrictName,
                'lat'      => $deliveryLat,
                'lng'      => $deliveryLng,
            ],

            'delivery_address' => $addr,
            'created_at'       => $order->created_at?->toIso8601String(),
            'placed_at'        => $order->placed_at,
        ];

        // eParcel — resolve districts from IDs
        if ($order->module_slug === 'eparcel' && $noteData) {
            $pickupInfo = $noteData['pickup'] ?? [];
            $senderDistrictId = $pickupInfo['district_id'] ?? null;
            $receiverDistrictId = $addr['district_id'] ?? null;
            $senderDist = $senderDistrictId ? DB::table('districts')->find($senderDistrictId) : null;
            $receiverDist = $receiverDistrictId ? DB::table('districts')->find($receiverDistrictId) : null;

            // Zone pricing for parcel
            if ($senderDist && $receiverDist && $zoneFee == 0) {
                $zone = DB::table('delivery_zone_pricing')->where('from_district_id', $senderDist->id)->where('to_district_id', $receiverDist->id)->where('is_active', true)->first();
                if ($zone) { $zoneFee = (float) $zone->base_price; $result['delivery_fee'] = $zoneFee; }
            }

            // Use district coordinates for map
            if ($senderDist && $result['pickup']['lat'] == 0) {
                $result['pickup']['lat'] = (float) $senderDist->latitude;
                $result['pickup']['lng'] = (float) $senderDist->longitude;
                $result['pickup']['district'] = $senderDist->name;
            }
            if ($receiverDist && $result['delivery']['lat'] == 0) {
                $result['delivery']['lat'] = (float) $receiverDist->latitude;
                $result['delivery']['lng'] = (float) $receiverDist->longitude;
                $result['delivery']['district'] = $receiverDist->name;
            }

            // Recalculate distance with correct coords
            if ($result['pickup']['lat'] != 0 && $result['delivery']['lat'] != 0) {
                $result['distance_km'] = round($this->haversine($result['pickup']['lat'], $result['pickup']['lng'], $result['delivery']['lat'], $result['delivery']['lng']), 1);
                $result['estimated_minutes'] = (int) round($result['distance_km'] * 3);
            }

            $result['parcel'] = [
                'sender_name'      => $pickupInfo['name'] ?? $order->user?->name,
                'sender_phone'     => $pickupInfo['phone'] ?? $order->user?->phone,
                'sender_district'  => $senderDist?->name,
                'receiver_name'    => $noteData['recipient'] ?? $noteData['receiver_name'] ?? null,
                'receiver_phone'   => $noteData['recipient_phone'] ?? $noteData['receiver_phone'] ?? null,
                'receiver_district'=> $receiverDist?->name,
                'package_type'     => $noteData['parcel_type'] ?? $noteData['package_type'] ?? null,
                'weight'           => $noteData['weight'] ?? null,
                'description'      => $noteData['description'] ?? null,
            ];

            $result['pickup']['name'] = $pickupInfo['name'] ?? $order->user?->name ?? 'Sender';
            $result['delivery']['name'] = $noteData['recipient'] ?? 'Receiver';
        }

        // eMoving — resolve districts by name, get zone pricing
        if ($order->module_slug === 'emoving' && $noteData) {
            $fromDistName = $noteData['from_district'] ?? null;
            $toDistName = $noteData['to_district'] ?? null;
            $fromDist = $fromDistName ? DB::table('districts')->where('name', $fromDistName)->first() : null;
            $toDist = $toDistName ? DB::table('districts')->where('name', $toDistName)->first() : null;

            // Moving route pricing (separate table from parcel zone pricing)
            $movingPrice = null;
            if ($fromDist && $toDist) {
                $moveType = $noteData['move_type'] ?? 'house';
                $movingPrice = DB::table('moving_pricing')
                    ->where('from_district_id', $fromDist->id)
                    ->where('to_district_id', $toDist->id)
                    ->where('move_type', $moveType)
                    ->where('is_active', true)
                    ->first();
            }

            // Use district coordinates for map
            if ($fromDist) {
                $result['pickup']['lat'] = (float) $fromDist->latitude;
                $result['pickup']['lng'] = (float) $fromDist->longitude;
                $result['pickup']['district'] = $fromDist->name;
                $result['pickup']['name'] = $fromDistName;
            }
            if ($toDist) {
                $result['delivery']['lat'] = (float) $toDist->latitude;
                $result['delivery']['lng'] = (float) $toDist->longitude;
                $result['delivery']['district'] = $toDist->name;
                $result['delivery']['name'] = $toDistName;
            }

            // Recalculate distance
            if ($result['pickup']['lat'] != 0 && $result['delivery']['lat'] != 0) {
                $result['distance_km'] = round($this->haversine($result['pickup']['lat'], $result['pickup']['lng'], $result['delivery']['lat'], $result['delivery']['lng']), 1);
                $result['estimated_minutes'] = (int) round($result['distance_km'] * 3);
            }

            // Moving pricing from moving_pricing table
            $distancePrice = $movingPrice ? (float) ($movingPrice->distance_price ?? 20) : (float) ($noteData['distance_fee'] ?? 20);

            // Driver earning = distance_price (what driver gets for this route)
            $result['delivery_fee'] = $distancePrice;

            $roomCount = (int) ($noteData['room_count'] ?? 0);
            $result['moving'] = [
                'customer_name'   => $noteData['customer_name'] ?? $order->user?->name,
                'customer_phone'  => $noteData['customer_phone'] ?? $order->user?->phone,
                'from_district'   => $fromDistName,
                'to_district'     => $toDistName,
                'from_address'    => $noteData['pickup_address'] ?? null,
                'to_address'      => $noteData['delivery_address'] ?? null,
                'moving_type'     => $noteData['move_type'] ?? $noteData['moving_type'] ?? null,
                'room_count'      => $roomCount > 0 ? $roomCount : null,
                'packages'        => $noteData['extra_services'] ?? $noteData['packages'] ?? $noteData['items'] ?? null,
                'scheduled_date'  => $noteData['scheduled_date'] ?? null,
                'distance_price'  => $distancePrice,
                'description'     => $noteData['user_note'] ?? $noteData['note'] ?? $noteData['description'] ?? null,
            ];
        }

        // eLaundry
        if ($order->module_slug === 'elaundry' && $noteData) {
            $result['laundry'] = [
                'service_type' => $noteData['service_type'] ?? null,
                'items'        => $noteData['items'] ?? null,
                'eta'          => $noteData['eta'] ?? null,
                'district'     => $noteData['district'] ?? $deliveryDistrict?->name,
            ];
        }

        return $result;
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
