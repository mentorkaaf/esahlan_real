<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Deliveryman;
use App\Models\DeliverymanDocument;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
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
            $order->update([
                'deliveryman_id' => $dm->id,
                'dispatched_at'  => now(),
            ]);

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

                // Credit delivery fee to driver wallet
                $fee = (float) ($order->delivery_fee ?? 0);
                if ($fee > 0) {
                    $wallet = Wallet::getOrCreateFor('App\\Models\\User', $request->user()->id);
                    $wallet->credit($fee, "Delivery: #{$order->order_number}", 'App\\Models\\Order', $order->id);
                }

                // Record earning
                DB::table('deliveryman_earnings')->insert([
                    'deliveryman_id' => $dm->id,
                    'order_id'       => $order->id,
                    'type'           => 'delivery_fee',
                    'amount'         => $fee,
                    'note'           => "Order #{$order->order_number}",
                    'created_at'     => now(),
                ]);

                // Credit vendor wallet (net earning after commission)
                if ($order->vendor_id) {
                    $vendorEarning = (float) $order->subtotal - (float) ($order->commission ?? 0);
                    if ($vendorEarning > 0) {
                        $vendorWallet = Wallet::getOrCreateFor('App\\Models\\Vendor', $order->vendor_id);
                        $vendorWallet->credit($vendorEarning, "Order #{$order->order_number} earning", 'App\\Models\\Order', $order->id);
                    }
                }

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
            }
        }

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
            ->select('deliveryman_earnings.*', 'orders.order_number', 'orders.module_slug')
            ->orderByDesc('deliveryman_earnings.created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'today'       => round((float) $todayEarnings, 2),
                'weekly'      => round((float) $weeklyEarnings, 2),
                'monthly'     => round((float) $monthlyEarnings, 2),
                'total'       => round((float) $totalEarnings, 2),
                'total_orders'=> $dm->total_deliveries,
                'rating'      => round($dm->rating ?? 5.0, 1),
                'daily_chart' => $dailyChart,
                'recent'      => $recentDeliveries,
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
        $vendorLat = $order->vendor?->latitude;
        $vendorLng = $order->vendor?->longitude;
        $addr = $order->delivery_address;
        if (is_string($addr)) $addr = json_decode($addr, true);
        $custLat = $addr['latitude'] ?? null;
        $custLng = $addr['longitude'] ?? null;

        $distance = null;
        if ($vendorLat && $vendorLng && $custLat && $custLng) {
            $distance = round($this->haversine($vendorLat, $vendorLng, $custLat, $custLng), 1);
        }

        // Parse parcel/laundry/moving details from note field
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
            'delivery_fee'    => (float) ($order->delivery_fee ?? 0),
            'items_count'     => $order->items?->count() ?? 0,
            'vendor'          => $order->vendor ? [
                'name'    => $order->vendor->name,
                'address' => $order->vendor->address,
                'phone'   => $order->vendor->phone,
                'lat'     => $order->vendor->latitude,
                'lng'     => $order->vendor->longitude,
                'logo'    => $order->vendor->logo,
            ] : null,
            'customer'        => $order->user ? [
                'name'    => $order->user->name,
                'phone'   => $order->user->phone,
                'lat'     => $order->user->latitude,
                'lng'     => $order->user->longitude,
            ] : null,
            'delivery_address'=> $addr,
            'distance_km'     => $distance,
            'created_at'      => $order->created_at?->toIso8601String(),
            'placed_at'       => $order->placed_at,
        ];

        // Parcel-specific details
        if ($order->module_slug === 'eparcel' && $noteData) {
            $result['parcel'] = [
                'sender_name'    => $noteData['sender_name'] ?? null,
                'sender_phone'   => $noteData['sender_phone'] ?? null,
                'sender_address' => $noteData['sender_address'] ?? $noteData['pickup_address'] ?? null,
                'receiver_name'  => $noteData['receiver_name'] ?? null,
                'receiver_phone' => $noteData['receiver_phone'] ?? null,
                'receiver_address'=> $noteData['receiver_address'] ?? $noteData['delivery_address'] ?? null,
                'package_type'   => $noteData['parcel_type'] ?? $noteData['package_type'] ?? null,
                'weight'         => $noteData['weight'] ?? null,
                'description'    => $noteData['description'] ?? $noteData['note'] ?? null,
            ];
        }

        // eMoving details
        if ($order->module_slug === 'emoving' && $noteData) {
            $result['moving'] = [
                'from_address'  => $noteData['from_address'] ?? $noteData['pickup_address'] ?? null,
                'to_address'    => $noteData['to_address'] ?? $noteData['delivery_address'] ?? null,
                'moving_type'   => $noteData['moving_type'] ?? null,
                'packages'      => $noteData['items'] ?? $noteData['packages'] ?? null,
                'description'   => $noteData['note'] ?? $noteData['description'] ?? null,
            ];
        }

        // Laundry details
        if ($order->module_slug === 'elaundry' && $noteData) {
            $result['laundry'] = [
                'service_type' => $noteData['service_type'] ?? null,
                'items'        => $noteData['items'] ?? null,
                'eta'          => $noteData['eta'] ?? null,
                'district'     => $noteData['district'] ?? null,
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
