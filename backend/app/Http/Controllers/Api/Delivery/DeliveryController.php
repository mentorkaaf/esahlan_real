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
use App\Services\AdminAlertService;
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

        // Admin alert — new driver registered via app
        try {
            AdminAlertService::send('new_agent', "New Driver Registered: {$user->name}", [
                'Name'         => $user->name,
                'Phone'        => $user->phone,
                'Driver Type'  => $request->driver_type ?? 'N/A',
                'Vehicle'      => $request->vehicle_type ?? 'N/A',
                'Status'       => 'Pending Approval',
                'Registered At'=> now()->format('d M Y H:i') . ' UTC',
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[AdminAlert][new_agent] ' . $e->getMessage());
        }

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
            ->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'out_for_delivery'])
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
        $radiusKm = (float) \App\Helpers\AppSettings::get('driver_notification_radius_km', 2);

        $driverLat = (float) ($dm->latitude ?? 0);
        $driverLng = (float) ($dm->longitude ?? 0);

        // Location must be fresh (within 10 min) to use proximity filter
        $locationFresh = $dm->last_location_at && \Carbon\Carbon::parse($dm->last_location_at)->gt(now()->subMinutes(10));

        $query = Order::whereNull('deliveryman_id')
            ->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup'])
            ->whereIn('module_slug', $allowedModules)
            ->with(['vendor:id,name,address,latitude,longitude,phone,logo,district_id', 'user:id,name,phone']);

        // Proximity filter — only show orders whose pickup is within radius of driver
        if ($driverLat && $driverLng && $locationFresh) {
            $query->where(function ($q) use ($driverLat, $driverLng, $radiusKm) {
                // eparcel: pickup district lat/lng stored in note JSON
                // emoving: from_district in note JSON
                // standard: vendor lat/lng (or vendor's district)
                // We filter by vendor lat/lng for standard, and fallback via subquery for parcel/moving
                $q->where(function ($std) use ($driverLat, $driverLng, $radiusKm) {
                    // Standard modules with vendor having direct coordinates
                    $std->whereNotNull('vendor_id')
                        ->whereHas('vendor', function ($v) use ($driverLat, $driverLng, $radiusKm) {
                            $v->whereRaw(
                                '(6371 * acos(cos(radians(?)) * cos(radians(latitude))
                                    * cos(radians(longitude) - radians(?))
                                    + sin(radians(?)) * sin(radians(latitude)))) <= ?',
                                [$driverLat, $driverLng, $driverLat, $radiusKm]
                            )->whereNotNull('latitude')->whereNotNull('longitude');
                        });
                })->orWhere(function ($distFallback) use ($driverLat, $driverLng, $radiusKm) {
                    // Vendor has no direct coordinates — use vendor's district
                    $distFallback->whereNotNull('vendor_id')
                        ->whereHas('vendor', function ($v) {
                            $v->whereNull('latitude')->orWhereNull('longitude');
                        })
                        ->whereHas('vendor.district', function ($d) use ($driverLat, $driverLng, $radiusKm) {
                            $d->whereRaw(
                                '(6371 * acos(cos(radians(?)) * cos(radians(latitude))
                                    * cos(radians(longitude) - radians(?))
                                    + sin(radians(?)) * sin(radians(latitude)))) <= ?',
                                [$driverLat, $driverLng, $driverLat, $radiusKm]
                            );
                        });
                })->orWhere(function ($noVendor) {
                    // eParcel / eMoving — no vendor; always include, distance checked after fetch
                    $noVendor->whereNull('vendor_id');
                });
            });
        }

        $orders = $query->latest()->limit(30)->get()
            ->map(fn($o) => $this->formatOrder($o, $dm))
            ->filter(function ($formatted) use ($driverLat, $driverLng, $radiusKm, $locationFresh) {
                // Post-filter: for eParcel/eMoving (no vendor), check pickup distance after format
                if (!$driverLat || !$driverLng || !$locationFresh) return true;
                $pLat = $formatted['pickup']['lat'] ?? 0;
                $pLng = $formatted['pickup']['lng'] ?? 0;
                if (!$pLat || !$pLng) return true; // no coords — include
                $dist = 6371 * acos(min(1, cos(deg2rad($driverLat)) * cos(deg2rad($pLat))
                    * cos(deg2rad($pLng) - deg2rad($driverLng))
                    + sin(deg2rad($driverLat)) * sin(deg2rad($pLat))));
                return $dist <= $radiusKm;
            })
            ->values();

        return response()->json(['success' => true, 'data' => $orders]);
    }

    public function activeOrders(Request $request)
    {
        $dm = $this->dm($request);
        if (!$dm) return response()->json(['success' => false, 'message' => 'Profile not found'], 404);

        // Only show orders the driver has explicitly ACCEPTED.
        // Admin-pre-assigned orders (driver_accepted_at IS NULL) are hidden until
        // the driver taps Accept on the ring screen.
        $orders = Order::where('deliveryman_id', $dm->id)
            ->whereNotNull('driver_accepted_at')
            ->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'out_for_delivery'])
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

        // Block if assigned to a DIFFERENT driver
        if ($order->deliveryman_id && $order->deliveryman_id !== $dm->id) {
            return response()->json(['success' => false, 'message' => 'Order already assigned to another driver'], 422);
        }

        // Idempotent: already accepted by this driver (e.g. double-tap)
        if ($order->deliveryman_id === $dm->id && $order->driver_accepted_at) {
            return response()->json(['success' => true, 'message' => 'Order accepted']);
        }

        // Max orders limit (admin configurable) — count only accepted orders
        $maxOrders = (int) \App\Helpers\AppSettings::get('max_orders_per_driver', 5);
        $activeCount = Order::where('deliveryman_id', $dm->id)
            ->whereNotNull('driver_accepted_at')
            ->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery'])
            ->count();
        if ($activeCount >= $maxOrders) {
            return response()->json(['success' => false, 'message' => "Maximum {$maxOrders} active orders. Complete existing deliveries first."], 422);
        }

        DB::transaction(function () use ($order, $dm, $request) {
            // For self-pick (pool): assign driver. For admin-pre-assigned: driver_id already set.
            $updateData = [
                'deliveryman_id'          => $dm->id,
                'dispatched_at'           => $order->dispatched_at ?? now(),
                'driver_accepted_at'      => now(),        // ← explicit acceptance timestamp
                'acceptance_status'       => 'accepted',   // ← admin can see "accepted"
                'acceptance_responded_at' => now(),
                'status'                  => 'out_for_delivery', // NOW set to out_for_delivery
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

        // Notify customer — driver accepted, include driver name
        try {
            $order->load(['user', 'deliveryman.user']);
            if ($order->user?->fcm_token) {
                $driverName  = $order->deliveryman?->user?->name ?? $dm->user?->name ?? 'a driver';
                $driverPhone = $order->deliveryman?->user?->phone ?? $dm->user?->phone ?? '';
                \App\Services\FcmService::sendDriverAssigned(
                    $order->user->fcm_token,
                    $order->order_number,
                    $order->id,
                    $order->module_slug,
                    $driverName,
                    $driverPhone
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

        $dm->increment('accepted_orders_count');
        return response()->json(['success' => true, 'message' => 'Order accepted']);
    }

    public function rejectOrder(Request $request, Order $order)
    {
        $dm = $this->dm($request);
        if ($dm) $dm->increment('rejected_orders_count');

        // If this order was admin-assigned to this driver (pending acceptance),
        // clear the assignment so admin knows the driver declined and can re-assign.
        if ($order->deliveryman_id && $dm && $order->deliveryman_id === $dm->id
            && ($order->acceptance_status === 'pending' || $order->acceptance_status === null)) {
            $order->update([
                'acceptance_status'       => 'declined',
                'acceptance_responded_at' => now(),
                'deliveryman_id'          => null,   // free the order for re-assignment
                'dispatched_at'           => null,
            ]);
            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => $order->status,
                'note'       => 'Driver ' . ($dm->user?->name ?? '#' . $dm->id) . ' DECLINED the order',
                'changed_by' => $request->user()->id,
            ]);
            // Restore driver to available (they were not set busy since acceptance was pending)
            $dm->update(['status' => 'available', 'is_available' => true]);
        }

        return response()->json(['success' => true, 'message' => 'Order declined']);
    }

    /**
     * Full order details for the ring screen.
     * Called by Flutter IncomingOrderScreen to load customer/vendor/parcel info
     * that is not included in the FCM payload.
     */
    public function ringOrder(Request $request, Order $order)
    {
        $dm = $this->dm($request);
        if (!$dm) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        // The driver must be assigned to this order (or it's available to them)
        if ($order->deliveryman_id && $order->deliveryman_id !== $dm->id) {
            return response()->json(['success' => false, 'message' => 'Not your order'], 403);
        }

        $formatted = $this->formatOrder($order, $dm);
        return response()->json(['success' => true, 'data' => $formatted]);
    }

    public function updateOrderStatus(Request $request, Order $order)
    {
        $dm = $this->dm($request);
        if (!$dm || $order->deliveryman_id !== $dm->id) {
            return response()->json(['success' => false, 'message' => 'Not your order'], 403);
        }

        $v = Validator::make($request->all(), [
            'status'       => 'required|in:picked_up,out_for_delivery,delivered',
            'note'         => 'nullable|string',
            'delivery_photo'=> 'nullable|image|max:5120',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        DB::transaction(function () use ($order, $request, $dm) {
            $data = ['status' => $request->status];

            if ($request->status === 'picked_up') {
                $data['picked_up_at'] = now();
            }

            if ($request->status === 'out_for_delivery') {
                $data['on_the_way_at'] = now();
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

                // Peak Pay Bonus — credit driver if order carried a bonus
                $bonusAmount = (float) ($order->bonus_amount ?? 0);
                if ($bonusAmount > 0) {
                    $bonusWallet = Wallet::getOrCreateFor('App\\Models\\User', $request->user()->id);
                    $bonusWallet->credit($bonusAmount, "🔥 Peak Pay Bonus: #{$order->order_number}", 'App\\Models\\Order', $order->id);
                    DB::table('deliveryman_earnings')->insert([
                        'deliveryman_id' => $dm->id,
                        'order_id'       => $order->id,
                        'type'           => 'bonus',
                        'amount'         => $bonusAmount,
                        'note'           => "Peak Pay Bonus — Order #{$order->order_number}",
                        'created_at'     => now(),
                    ]);
                }

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
                $dm->increment('completed_orders_count');
                try { \App\Services\ChallengeService::onDeliveryComplete($dm); } catch (\Throwable) {}
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
        // Auto-mark driver as online whenever a location ping arrives.
        // This ensures the live map shows the driver even if they forgot to
        // toggle "go online" in the app, and keeps the status fresh as long
        // as the background service is posting locations.
        $speed    = $request->speed    !== null ? (float) $request->speed    : null;
        $heading  = $request->heading  !== null ? (float) $request->heading  : null;
        $battery  = $request->battery_level !== null ? (int) $request->battery_level : null;
        $accuracy = $request->accuracy !== null ? (float) $request->accuracy : null;

        $updateData = [
            'latitude'        => $request->latitude,
            'longitude'       => $request->longitude,
            'last_location_at'=> now(),
            'missed_pings'    => 0,   // reset reliability counter on every successful ping
        ];
        if ($speed   !== null) $updateData['speed']   = $speed;
        if ($heading !== null) $updateData['heading']  = $heading;
        if ($battery !== null) $updateData['battery_level'] = $battery;

        // Only auto-mark online if the driver has NOT explicitly gone offline via the toggle.
        // status='offline' means the driver deliberately toggled off — respect that choice.
        // Only auto-online drivers who have never toggled (status != 'offline').
        if (!$dm->is_online && $dm->status !== 'offline') {
            $updateData['is_online'] = true;
            $updateData['status']    = 'available';
        }
        // Every location ping implies the driver wants to be tracked.
        // This seeds wants_tracking=true for drivers on older app versions
        // that don't set it via the toggle endpoint.
        if (!$dm->wants_tracking) {
            $updateData['wants_tracking'] = true;
        }
        $dm->update($updateData);

        // ── Append to route history (speed, heading, battery, accuracy) ──────
        DB::table('driver_location_history')->insert([
            'deliveryman_id' => $dm->id,
            'latitude'       => $request->latitude,
            'longitude'      => $request->longitude,
            'speed'          => $speed,
            'heading'        => $heading,
            'accuracy'       => $accuracy,
            'battery_level'  => $battery,
            'created_at'     => now(),
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
                // Broadcast to order-chat channel for customer real-time tracking
                broadcast(new \App\Events\OrderDriverLocationUpdated(
                    $order->id,
                    (float) $request->latitude,
                    (float) $request->longitude,
                ));
            }
        }

        // Broadcast to admin drivers channel for live map (with telemetry)
        broadcast(new \App\Events\DriverLocationUpdated(
            deliverymanId: $dm->id,
            userId:        $dm->user_id,
            name:          $dm->user?->name ?? 'Driver',
            lat:           (float) $request->latitude,
            lng:           (float) $request->longitude,
            status:        $dm->status,
            orderId:       $request->order_id ? (int) $request->order_id : null,
            speed:         $speed,
            heading:       $heading,
            batteryLevel:  $battery,
            missedPings:   0,
        ))->toOthers();

        return response()->json(['success' => true]);
    }

    public function toggleStatus(Request $request)
    {
        $dm = $this->dm($request);
        if (!$dm) return response()->json(['success' => false], 404);

        $goingOnline = !$dm->is_online;
        $dm->update([
            'is_online'      => $goingOnline,
            'is_available'   => $goingOnline,
            'status'         => $goingOnline ? 'available' : 'offline',
            // wants_tracking records the DRIVER's INTENT so the FCM ping
            // scheduler can wake the app even after MarkStaleDriversOffline
            // has temporarily cleared is_online.
            'wants_tracking' => $goingOnline,
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

            // Use real GPS coords from order if present, else fall back to district center
            $realPickupLat = isset($pickupInfo['lat']) ? (float) $pickupInfo['lat'] : 0;
            $realPickupLng = isset($pickupInfo['lng']) ? (float) $pickupInfo['lng'] : 0;
            if ($realPickupLat != 0 && $realPickupLng != 0) {
                $result['pickup']['lat'] = $realPickupLat;
                $result['pickup']['lng'] = $realPickupLng;
                if ($senderDist) $result['pickup']['district'] = $senderDist->name;
            } elseif ($senderDist && $result['pickup']['lat'] == 0) {
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

        // Driver → pickup distance (how far the driver needs to travel to collect the order)
        $dLat = (float) ($dm->latitude ?? 0);
        $dLng = (float) ($dm->longitude ?? 0);
        $pLat = (float) ($result['pickup']['lat'] ?? 0);
        $pLng = (float) ($result['pickup']['lng'] ?? 0);
        $result['driver_to_pickup_km'] = ($dLat && $dLng && $pLat && $pLng)
            ? round($this->haversine($dLat, $dLng, $pLat, $pLng), 1)
            : null;

        return $result;
    }


    // STATS
    public function stats(Request $request) {
        $dm = $this->dm($request);
        if (!$dm) return response()->json(['success'=>false],404);
        $total = $dm->accepted_orders_count + $dm->rejected_orders_count;
        $ar = $total > 0 ? round($dm->accepted_orders_count / $total * 100, 1) : 100.0;
        $cr = $dm->accepted_orders_count > 0 ? round($dm->completed_orders_count / $dm->accepted_orders_count * 100, 1) : 100.0;
        $tier = $this->computeTier($dm, $ar, $cr);
        return response()->json(['success'=>true,'data'=>[
            'acceptance_rate'=>$ar,'completion_rate'=>$cr,
            'accepted_count'=>$dm->accepted_orders_count,'rejected_count'=>$dm->rejected_orders_count,
            'completed_count'=>$dm->completed_orders_count,'total_deliveries'=>$dm->total_deliveries,
            'rating'=>round($dm->rating??5.0,1),'tier'=>$tier,'tier_next'=>$this->nextTier($tier)]]);
    }

    private function computeTier(Deliveryman $dm, float $ar, float $cr): string {
        $td=(int)($dm->total_deliveries??0); $rt=(float)($dm->rating??5.0);
        if($td>=500&&$rt>=4.8&&$ar>=90) return 'diamond';
        if($td>=200&&$rt>=4.5&&$ar>=85) return 'gold';
        if($td>=50&&$rt>=3.5&&$ar>=70)  return 'silver';
        return 'bronze';
    }

    private function nextTier(string $tier): ?array {
        return ['bronze'=>['name'=>'Silver','deliveries'=>50,'rating'=>3.5,'acceptance'=>70],
                'silver'=>['name'=>'Gold','deliveries'=>200,'rating'=>4.5,'acceptance'=>85],
                'gold'  =>['name'=>'Diamond','deliveries'=>500,'rating'=>4.8,'acceptance'=>90],
                'diamond'=>null][$tier]??null;
    }

    // ── SOS EMERGENCY ────────────────────────────────────────────────────────────
    public function sos(Request $request) {
        $dm = $this->dm($request);
        if (!$dm) return response()->json(['success' => false], 404);
        $dm->loadMissing('user:id,name,phone,email');

        $driverName  = $dm->user?->name  ?? 'Driver #' . $dm->id;
        $driverPhone = $dm->user?->phone ?? '—';
        $lat         = $request->latitude;
        $lng         = $request->longitude;
        $message     = $request->message ?? 'SOS — driver needs help';
        $orderId     = $request->order_id;

        // 1. Save to DB
        $alertId = DB::table('driver_sos_alerts')->insertGetId([
            'deliveryman_id' => $dm->id,
            'driver_name'    => $driverName,
            'driver_phone'   => $driverPhone,
            'order_id'       => $orderId,
            'latitude'       => $lat,
            'longitude'      => $lng,
            'message'        => $message,
            'status'         => 'active',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // 2. FCM to ALL admin/super_admin users — high-priority alarm
        $adminUsers = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->whereIn('roles.slug', ['super_admin', 'admin', 'operations_manager'])
            ->get(['users.id', 'users.email', 'users.fcm_token']);

        $mapsUrl = ($lat && $lng)
            ? "https://maps.google.com/?q={$lat},{$lng}"
            : null;

        foreach ($adminUsers as $admin) {
            // FCM push
            if ($admin->fcm_token) {
                try {
                    \App\Services\FcmService::sendSosAlert(
                        $admin->fcm_token,
                        $driverName,
                        $driverPhone,
                        $message,
                        $alertId,
                        $lat,
                        $lng
                    );
                } catch (\Throwable) {}
            }

            // Email
            if ($admin->email) {
                try {
                    \Illuminate\Support\Facades\Mail::send([], [], function ($mail) use (
                        $admin, $driverName, $driverPhone, $message, $mapsUrl, $alertId, $orderId
                    ) {
                        $mail->to($admin->email)
                            ->subject("🆘 SOS EMERGENCY — {$driverName}")
                            ->html(
                                "<div style='font-family:sans-serif;max-width:600px;margin:auto;'>" .
                                "<div style='background:#EF4444;padding:24px;border-radius:12px 12px 0 0;text-align:center;'>" .
                                "<h1 style='color:#fff;margin:0;font-size:28px;'>🆘 SOS EMERGENCY</h1>" .
                                "</div>" .
                                "<div style='background:#fff;padding:24px;border:1px solid #e5e7eb;border-radius:0 0 12px 12px;'>" .
                                "<table style='width:100%;border-collapse:collapse;'>" .
                                "<tr><td style='padding:8px;color:#6b7280;font-weight:600;width:140px;'>Driver</td>" .
                                "<td style='padding:8px;font-weight:700;font-size:16px;'>{$driverName}</td></tr>" .
                                "<tr style='background:#f9fafb;'><td style='padding:8px;color:#6b7280;font-weight:600;'>Phone</td>" .
                                "<td style='padding:8px;'><a href='tel:{$driverPhone}' style='color:#f97316;font-weight:700;'>{$driverPhone}</a></td></tr>" .
                                ($orderId ? "<tr><td style='padding:8px;color:#6b7280;font-weight:600;'>Order</td><td style='padding:8px;'>#{$orderId}</td></tr>" : "") .
                                "<tr style='background:#f9fafb;'><td style='padding:8px;color:#6b7280;font-weight:600;'>Message</td>" .
                                "<td style='padding:8px;'>{$message}</td></tr>" .
                                ($mapsUrl ? "<tr><td style='padding:8px;color:#6b7280;font-weight:600;'>Location</td>" .
                                "<td style='padding:8px;'><a href='{$mapsUrl}' style='background:#EF4444;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none;font-weight:700;'>📍 View on Map</a></td></tr>" : "") .
                                "</table>" .
                                "<div style='margin-top:20px;padding:16px;background:#FEF2F2;border-radius:8px;border-left:4px solid #EF4444;'>" .
                                "<p style='margin:0;color:#991B1B;font-weight:700;'>Respond immediately — driver may be in danger.</p>" .
                                "<p style='margin:8px 0 0;'><a href='" . url("/admin/sos/{$alertId}") . "' style='background:#EF4444;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;font-weight:700;display:inline-block;'>Open SOS Dashboard</a></p>" .
                                "</div></div></div>"
                            );
                    });
                } catch (\Throwable) {}
            }
        }

        // 3. Admin Alert email (admin/alerts system — toggleable from settings)
        try {
            $alertData = [
                'Driver'   => $driverName,
                'Phone'    => $driverPhone,
                'Message'  => $message,
            ];
            if ($orderId) $alertData['Order ID'] = '#' . $orderId;
            if ($lat && $lng) $alertData['Location'] = "https://maps.google.com/?q={$lat},{$lng}";
            $alertData['Dashboard'] = url("/admin/sos/{$alertId}");

            \App\Services\AdminAlertService::send(
                'sos_alert',
                "🆘 SOS EMERGENCY — {$driverName} ({$driverPhone})",
                $alertData
            );
        } catch (\Throwable) {}

        // 4. Broadcast via Reverb to admin dashboard (real-time alarm)
        try {
            event(new \App\Events\DriverSosAlert([
                'alert_id'    => $alertId,
                'driver_id'   => $dm->id,
                'driver_name' => $driverName,
                'driver_phone'=> $driverPhone,
                'lat'         => $lat,
                'lng'         => $lng,
                'message'     => $message,
                'order_id'    => $orderId,
                'created_at'  => now()->toISOString(),
            ]));
        } catch (\Throwable) {}

        return response()->json(['success' => true, 'message' => 'SOS sent to all admins', 'alert_id' => $alertId]);
    }

    // HEATMAP
    public function heatmap(Request $request) {
        $dm = $this->dm($request);
        if (!$dm) return response()->json(['success'=>false],404);

        // Per-vendor active order counts — real location, real name
        $rows = DB::table('orders')
            ->join('vendors', 'orders.vendor_id', '=', 'vendors.id')
            ->where('orders.created_at', '>=', now()->subHours(4))
            ->whereIn('orders.status', ['pending','confirmed','preparing','ready_for_pickup','out_for_delivery'])
            ->whereNotNull('vendors.latitude')
            ->whereNotNull('vendors.longitude')
            ->select(
                'vendors.id as vendor_id',
                'vendors.name as vendor_name',
                'vendors.logo',
                'vendors.latitude',
                'vendors.longitude',
                DB::raw('COUNT(*) as order_count')
            )
            ->groupBy('vendors.id', 'vendors.name', 'vendors.logo', 'vendors.latitude', 'vendors.longitude')
            ->get();

        // Active delivery bonus
        $bonus       = DB::table('delivery_bonus_settings')->where('is_active', true)->first();
        $bonusAmount = $bonus ? (float)$bonus->bonus_amount : 0;

        $zones = [];
        foreach ($rows as $row) {
            $count = (int) $row->order_count;
            $level = match(true) {
                $count >= 5 => 'very_busy',
                $count >= 2 => 'busy',
                default     => 'active',
            };

            $zones[] = [
                'vendor_id'    => $row->vendor_id,
                'vendor_name'  => $row->vendor_name,
                'logo'         => $row->logo ? asset('storage/' . $row->logo) : null,
                'lat'          => (float) $row->latitude,
                'lng'          => (float) $row->longitude,
                'order_count'  => $count,
                'level'        => $level,
                'bonus_amount' => $bonusAmount,
                'label'        => match($level) {
                    'very_busy' => 'Very Busy',
                    'busy'      => 'Busy',
                    default     => 'Active',
                } . ($bonusAmount > 0 ? ' +$' . number_format($bonusAmount, 2) : ''),
                'radius_m'     => match($level) {
                    'very_busy' => 900,
                    'busy'      => 650,
                    default     => 450,
                },
            ];
        }

        return response()->json(['success' => true, 'data' => $zones]);
    }

    // CHALLENGES
    public function challenges(Request $request) {
        $dm = $this->dm($request);
        if (!$dm) return response()->json(['success'=>false],404);
        $now = now();
        $challenges = DB::table('driver_challenges')->where('is_active',true)
            ->where(fn($q)=>$q->whereNull('starts_at')->orWhere('starts_at','<=',$now))
            ->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>=',$now))->get();
        $result=[];
        foreach($challenges as $ch){
            $prog=DB::table('driver_challenge_progress')
                ->where('deliveryman_id',$dm->id)->where('challenge_id',$ch->id)->first();
            $result[]=['id'=>$ch->id,'title'=>$ch->title,'description'=>$ch->description,
                'type'=>$ch->type,'target_count'=>$ch->target_count,'reward_amount'=>(float)$ch->reward_amount,
                'ends_at'=>$ch->ends_at,'current_count'=>$prog->current_count??0,
                'completed'=>$prog?->completed_at!==null,'reward_paid'=>$prog?->reward_paid_at!==null,
                'pct'=>$ch->target_count>0?min(100,round(($prog->current_count??0)/$ch->target_count*100)):0];
        }
        return response()->json(['success'=>true,'data'=>$result]);
    }

    // ══════════════════════════════════════════════════════════════
    // BONUS STATUS — current peak-pay bonus for the driver
    // ══════════════════════════════════════════════════════════════
    public function bonusStatus(Request $request)
    {
        $bonus = DB::table('delivery_bonus_settings')->where('is_active', true)->first();

        if (!$bonus) {
            return response()->json(['success' => true, 'data' => ['is_active' => false]]);
        }

        $now         = now();
        $currentHour = (int) $now->format('G');  // 0–23
        $currentDay  = (int) $now->format('N');  // 1=Mon…7=Sun

        $days = array_filter(array_map('intval', explode(',', $bonus->days_of_week)));
        $inDay  = in_array($currentDay, $days);
        $inTime = $currentHour >= $bonus->start_hour && $currentHour < $bonus->end_hour;

        if (!$inDay || !$inTime) {
            // Bonus setting is on but outside schedule — return next window info
            return response()->json(['success' => true, 'data' => [
                'is_active'     => false,
                'next_label'    => $bonus->label,
                'next_amount'   => (float) $bonus->bonus_amount,
                'next_start_hr' => $bonus->start_hour,
            ]]);
        }

        $endsInMinutes = ($bonus->end_hour - $currentHour) * 60 - (int) $now->format('i');

        return response()->json(['success' => true, 'data' => [
            'is_active'       => true,
            'bonus_amount'    => (float) $bonus->bonus_amount,
            'label'           => $bonus->label,
            'ends_in_minutes' => max(0, $endsInMinutes),
        ]]);
    }

    // AUTO-DISPATCH (static)
    public static function autoDispatch(Order $order): bool {
        $isTruck=in_array($order->module_slug,['emoving']);
        $pLat=(float)($order->vendor?->latitude??0);
        $pLng=(float)($order->vendor?->longitude??0);
        $radius=(float)\App\Helpers\AppSettings::get('driver_notification_radius_km',5);
        $drivers=Deliveryman::where('is_online',true)->where('is_available',true)
            ->where('is_approved',true)->where('status','available')
            ->when($isTruck,fn($q)=>$q->where('driver_type','truck'))
            ->when(!$isTruck,fn($q)=>$q->where(fn($q)=>$q->where('driver_type','!=','truck')->orWhereNull('driver_type')))
            ->whereNotNull('latitude')->where('last_location_at','>=',now()->subMinutes(15))->get();
        if($drivers->isEmpty()) return false;
        $tierOrder=['diamond'=>4,'gold'=>3,'silver'=>2,'bronze'=>1];
        $candidates=$drivers->map(function($dm) use($pLat,$pLng,$tierOrder){
            $lat=(float)$dm->latitude;$lng=(float)$dm->longitude;
            $dist=($lat&&$lng&&$pLat)?6371*acos(min(1,cos(deg2rad($pLat))*cos(deg2rad($lat))*cos(deg2rad($lng)-deg2rad($pLng))+sin(deg2rad($pLat))*sin(deg2rad($lat)))):999;
            $total=$dm->accepted_orders_count+$dm->rejected_orders_count;
            $ar=$total>0?$dm->accepted_orders_count/$total*100:100;
            $td=(int)($dm->total_deliveries??0);$rt=(float)($dm->rating??5.0);
            $tier='bronze';
            if($td>=500&&$rt>=4.8&&$ar>=90)$tier='diamond';
            elseif($td>=200&&$rt>=4.5&&$ar>=85)$tier='gold';
            elseif($td>=50&&$rt>=3.5&&$ar>=70)$tier='silver';
            return ['dm'=>$dm,'dist'=>$dist,'tier_score'=>$tierOrder[$tier]??1];
        })->filter(fn($c)=>$c['dist']<=($pLat?$radius*3:999))
          ->sortByDesc('tier_score')->sortBy('dist');
        if($candidates->isEmpty()) return false;
        $best=$candidates->first();$dm=$best['dm'];
        $token=$dm->fcm_token;if(!$token) return false;
        $addr=$order->delivery_address;if(is_string($addr))$addr=json_decode($addr,true);
        \App\Services\FcmService::sendNewOrderRing($token,[
            'id'=>$order->id,'order_number'=>$order->order_number,
            'module_slug'=>$order->module_slug??'order','delivery_fee'=>(float)($order->delivery_fee??0),
            'distance'=>round($best['dist'],1),'estimated_minutes'=>(int)($best['dist']*3),
            'driver_to_pickup_km'=>round($best['dist'],1),
            'pickup_address'=>['lat'=>$pLat,'lng'=>$pLng,'district'=>$order->vendor?->district?->name??''],
            'delivery_address'=>is_array($addr)?$addr:[]]);
        return true;
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
