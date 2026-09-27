<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Deliveryman;
use App\Models\OrderStatusHistory;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DispatchController extends Controller
{
    public function index()
    {
        $activeOrders = Order::with(['vendor:id,name,latitude,longitude,logo', 'user:id,name,phone,latitude,longitude', 'deliveryman.user:id,name,phone'])
            ->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery'])
            ->latest()
            ->get();

        $availableDrivers = Deliveryman::with('user:id,name,phone')
            ->where('is_approved', true)
            ->whereIn('status', ['available', 'busy'])
            ->get();

        $stats = [
            'pending'   => $activeOrders->where('status', 'pending')->count(),
            'confirmed' => $activeOrders->where('status', 'confirmed')->count(),
            'ready'     => $activeOrders->where('status', 'ready_for_pickup')->count(),
            'delivering'=> $activeOrders->where('status', 'out_for_delivery')->count(),
            'drivers_online'    => $availableDrivers->where('status', 'available')->count(),
            'drivers_busy'      => $availableDrivers->where('status', 'busy')->count(),
            'unassigned'=> $activeOrders->whereNull('deliveryman_id')->count(),
        ];

        return view('admin.dispatch.index', compact('activeOrders', 'availableDrivers', 'stats'));
    }

    /** Strip emoji and mojibake chars from a string for safe display */
    private static function cleanName(?string $s): string
    {
        if (!$s) return '';
        // Remove emoji (4-byte Unicode: U+1F000 and above) and their variation selectors
        $s = preg_replace('/[\x{1F000}-\x{1FFFF}][\x{FE00}-\x{FEFF}]?/u', '', $s);
        // Remove any remaining non-BMP surrogates or replacement chars
        $s = preg_replace('/[\x{FFF0}-\x{FFFF}]/u', '', $s);
        // Remove bytes in 0x80-0xBF range that are orphaned UTF-8 continuation bytes (mojibake)
        $s = preg_replace('/[\x80-\xBF]+/', '', $s);
        return trim($s);
    }

    public function activeOrders()
    {
        $orders = Order::with(['vendor:id,name,latitude,longitude,phone,logo,district_id', 'user:id,name,phone,latitude,longitude,district_id', 'deliveryman.user:id,name,phone'])
            ->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery'])
            ->latest()
            ->get()
            ->map(function ($o) {
                $addr = $o->delivery_address;
                if (is_string($addr)) $addr = json_decode($addr, true);

                return [
                    'id'            => $o->id,
                    'order_number'  => $o->order_number,
                    'status'        => $o->status,
                    'module_slug'   => $o->module_slug,
                    'total'         => (float) ($o->total_amount ?? 0),
                    'delivery_fee'  => (float) ($o->delivery_fee ?? 0),
                    'customer_name' => self::cleanName($o->user?->name),
                    'customer_phone'=> $o->user?->phone,
                    'customer_lat'  => (float) ($o->user?->latitude ?? 0),
                    'customer_lng'  => (float) ($o->user?->longitude ?? 0),
                    'vendor_name'   => self::cleanName($o->vendor?->name),
                    'vendor_lat'    => (float) ($o->vendor?->latitude ?? 0),
                    'vendor_lng'    => (float) ($o->vendor?->longitude ?? 0),
                    'vendor_logo'   => $o->vendor?->logo,
                    'driver_name'       => self::cleanName($o->deliveryman?->user?->name),
                    'driver_id'         => $o->deliveryman_id,
                    'acceptance_status' => $o->acceptance_status,  // null/pending/accepted/declined
                    'placed_at'         => $o->created_at?->diffForHumans(),
                    'district'          => $addr['district'] ?? $addr['city'] ?? null,
                ];
            });

        return response()->json(['data' => $orders]);
    }

    public function availableDeliverymen()
    {
        $deliverymen = Deliveryman::with('user:id,name,phone')
            ->where('is_approved', true)
            ->whereIn('status', ['available', 'busy'])
            ->get()
            ->map(function ($d) {
                return [
                    'id'           => $d->id,
                    'name'         => $d->user?->name,
                    'phone'        => $d->user?->phone,
                    'vehicle_type' => $d->vehicle_type,
                    'status'       => $d->status,
                    'latitude'     => (float) ($d->latitude ?? 0),
                    'longitude'    => (float) ($d->longitude ?? 0),
                    'rating'       => round($d->rating ?? 5, 1),
                    'total_deliveries' => $d->total_deliveries,
                    'last_seen'    => $d->last_location_at ? \Carbon\Carbon::parse($d->last_location_at)->diffForHumans() : null,
                ];
            });

        return response()->json(['data' => $deliverymen]);
    }

    public function manualAssign(Request $request)
    {
        $request->validate([
            'order_id'       => 'required|exists:orders,id',
            'deliveryman_id' => 'required|exists:deliverymen,id',
        ]);

        $order = Order::findOrFail($request->order_id);
        $dm = Deliveryman::findOrFail($request->deliveryman_id);

        DB::transaction(function () use ($order, $dm) {
            // Assign driver but DON'T set out_for_delivery yet.
            // acceptance_status = 'pending' means we are WAITING for driver to accept.
            // Status will change to out_for_delivery only when driver accepts via the app.
            $order->update([
                'deliveryman_id'    => $dm->id,
                'dispatched_at'     => now(),
                'acceptance_status' => 'pending',
                'driver_accepted_at'=> null,  // clear any previous acceptance
            ]);

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => $order->status,
                'note'       => 'Assigned to ' . ($dm->user?->name ?? 'driver') . ' — waiting for acceptance',
                'changed_by' => auth()->id(),
            ]);

            // Don't set driver busy yet — only after they accept
            // $dm->update(['status' => 'busy', 'is_available' => false]);
        });

        // Notify driver — full ring alarm with order details
        try {
            $driverToken = $dm->fcm_token ?? $dm->user?->fcm_token;
            if ($driverToken) {
                // Load related order data for the alarm payload
                $order->load(['pickupAddress', 'deliveryAddress']);

                // Build flat pickup/delivery arrays (works for all module types)
                $pickupInfo   = $order->pickup_address   ?? $order->pickupAddress   ?? null;
                $deliveryInfo = $order->delivery_address ?? $order->deliveryAddress ?? null;

                $pickupArr   = is_array($pickupInfo)   ? $pickupInfo   : (is_object($pickupInfo)   ? $pickupInfo->toArray()   : []);
                $deliveryArr = is_array($deliveryInfo) ? $deliveryInfo : (is_object($deliveryInfo) ? $deliveryInfo->toArray() : []);

                // Try to pull district/address from stored JSON columns if present
                if (empty($pickupArr) && $order->getRawOriginal('pickup_address')) {
                    $pickupArr   = json_decode($order->getRawOriginal('pickup_address'), true) ?? [];
                }
                if (empty($deliveryArr) && $order->getRawOriginal('delivery_address')) {
                    $deliveryArr = json_decode($order->getRawOriginal('delivery_address'), true) ?? [];
                }

                \App\Services\FcmService::sendNewOrderRing($driverToken, [
                    'id'           => $order->id,
                    'order_number' => $order->order_number,
                    'module_slug'  => $order->module_slug,
                    'delivery_fee' => $order->delivery_fee ?? 0,
                    'distance'     => $order->distance ?? 0,
                    'estimated_minutes' => $order->estimated_minutes ?? 0,
                    'driver_to_pickup_km' => 0,  // set to 0 — driver sees it on map
                    'pickup_address'   => $pickupArr,
                    'delivery_address' => $deliveryArr,
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('[Dispatch] sendNewOrderRing failed: ' . $e->getMessage());
        }

        // Notify customer — driver assigned
        try {
            $order->load('user');
            if ($order->user?->fcm_token) {
                $driverName = $dm->user?->name ?? 'a driver';
                $driverPhone = $dm->user?->phone ?? '';
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

        return response()->json(['success' => true, 'message' => 'Driver assigned — waiting for acceptance.']);
    }

    /**
     * Force a driver online from the admin Live Tracking panel.
     * Sets is_online=true in DB and sends FCM reminder to driver to open app.
     *
     * POST /admin/dispatch/drivers/{id}/force-online
     */
    public function forceDriverOnline(Request $request, int $deliverymanId)
    {
        $dm = Deliveryman::with('user:id,name,fcm_token')->find($deliverymanId);
        if (!$dm) return response()->json(['success' => false, 'message' => 'Driver not found'], 404);

        // Set online in DB — wants_tracking=true ensures FCM pings keep firing
        // even if the driver's foreground service is dead (killed-app scenario).
        $dm->update([
            'is_online'      => true,
            'status'         => $dm->status === 'offline' ? 'available' : $dm->status,
            'is_available'   => true,
            'wants_tracking' => true,
        ]);

        // Send FCM reminder to open app / start location service
        $fcmToken = $dm->user?->fcm_token ?? $dm->fcm_token;
        $sent = false;
        if ($fcmToken) {
            try {
                $sent = \App\Services\FcmService::sendDataOnly($fcmToken, [
                    'type'    => 'force_online_reminder',
                    'message' => 'Admin has set you online. Please open the app to start location tracking.',
                ]);
            } catch (\Throwable $e) {
                \Log::warning('[Dispatch] forceOnline FCM failed: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success'     => true,
            'driver_name' => $dm->user?->name ?? 'Driver #' . $dm->id,
            'message'     => 'Driver set online' . ($sent ? ' + FCM reminder sent' : ' (no FCM token)'),
        ]);
    }

    public function liveMap()
    {
        return view('admin.dispatch.live_map');
    }

    public function liveDrivers()
    {
        // Show ALL approved drivers — online and offline — so admin has full visibility.
        // Offline drivers appear on map with grey markers (no location filter).
        $drivers = Deliveryman::with('user:id,name,phone')
            ->where('is_approved', true)
            ->get()
            ->map(function ($d) {
                $activeOrder = null;
                $activeOrdersCount = 0;
                if ($d->status === 'busy') {
                    $activeOrdersCount = DB::table('orders')
                        ->where('deliveryman_id', $d->id)
                        ->whereIn('status', ['out_for_delivery', 'ready_for_pickup', 'confirmed'])
                        ->count();
                    $activeOrder = DB::table('orders')
                        ->where('deliveryman_id', $d->id)
                        ->whereIn('status', ['out_for_delivery', 'ready_for_pickup'])
                        ->select('id', 'order_number', 'status', 'module_slug', 'delivery_address', 'vendor_id')
                        ->first();
                    if ($activeOrder) {
                        $vendor = DB::table('vendors')->where('id', $activeOrder->vendor_id)->select('latitude', 'longitude')->first();
                        $activeOrder->pickup_lat = $vendor ? (float) $vendor->latitude : null;
                        $activeOrder->pickup_lng = $vendor ? (float) $vendor->longitude : null;
                        $addr = $activeOrder->delivery_address;
                        if (is_string($addr)) $addr = json_decode($addr, true);
                        $activeOrder->delivery_lat = is_array($addr) && isset($addr['lat']) ? (float) $addr['lat'] : null;
                        $activeOrder->delivery_lng = is_array($addr) && isset($addr['lng']) ? (float) $addr['lng'] : null;
                        unset($activeOrder->delivery_address, $activeOrder->vendor_id);
                    }
                }
                // Reliability: % of expected pings received (simple rolling metric)
                $staleMin = $d->last_location_at
                    ? \Carbon\Carbon::parse($d->last_location_at)->diffInMinutes(now())
                    : 9999;
                $reliability = $d->missed_pings > 0
                    ? max(0, round(100 - ($d->missed_pings * 8)))  // -8% per missed ping
                    : 100;

                $isReallyOnline = (bool) $d->is_online && $staleMin <= 10;

                return [
                    'id'           => $d->id,
                    'name'         => self::cleanName($d->user?->name) ?: 'Driver #' . $d->id,
                    'phone'        => $d->user?->phone,
                    'vehicle_type' => $d->vehicle_type,
                    'status'       => $d->is_online ? $d->status : 'offline',
                    'latitude'     => $d->latitude  ? (float) $d->latitude  : null,
                    'longitude'    => $d->longitude ? (float) $d->longitude : null,
                    'last_seen'    => $d->last_location_at
                                        ? \Carbon\Carbon::parse($d->last_location_at)->diffForHumans()
                                        : 'Never',
                    'last_seen_at' => $d->last_location_at,
                    'is_online'    => (bool) $d->is_online,
                    'is_really_online' => $isReallyOnline,
                    'is_stale'           => $staleMin > 5,
                    'rating'             => round($d->rating ?? 5, 1),
                    'order'              => $activeOrder,
                    'active_orders_count'=> $activeOrdersCount,
                    // Telemetry
                    'speed'         => $d->speed !== null ? round((float)$d->speed * 3.6, 1) : null,
                    'heading'       => $d->heading !== null ? (float) $d->heading : null,
                    'battery_level' => $d->battery_level !== null ? (int) $d->battery_level : null,
                    'missed_pings'  => (int) ($d->missed_pings ?? 0),
                    'reliability'   => $reliability,
                    'has_fcm'       => !empty($d->user?->fcm_token ?? $d->fcm_token),
                ];
            });

        return response()->json(['data' => $drivers, 'updated_at' => now()->toISOString()]);
    }

    /**
     * Push a silent FCM ping to a driver requesting them to send location now.
     * Works even when app is in background (FCM high-priority wakes the app).
     */
    public function requestLocation(Request $request, int $deliverymanId)
    {
        $dm = Deliveryman::with('user:id,name')->find($deliverymanId);
        if (!$dm) return response()->json(['success' => false, 'message' => 'Driver not found'], 404);

        $fcmToken = DB::table('users')
            ->where('id', $dm->user_id)
            ->value('fcm_token');

        if (!$fcmToken) {
            return response()->json(['success' => false, 'message' => 'No FCM token for this driver'], 404);
        }

        // Send high-priority silent push — wakes the driver app to post location
        $ok = \App\Services\FcmService::sendLocationRequest($fcmToken);

        return response()->json([
            'success'     => $ok,
            'driver_name' => $dm->user?->name,
            'message'     => $ok ? 'Location request sent' : 'FCM send failed',
        ]);
    }

    /**
     * Return the GPS trail for one driver (for admin map polyline).
     *
     * GET /admin/dispatch/driver/{id}/route?minutes=60
     *
     * Response: { success: true, driver_id, name, points: [{lat, lng, ts}] }
     * Max window: 1440 min (24 h). Default: 60 min.
     * Points are ordered oldest → newest so the polyline draws correctly.
     */
    public function driverRoute(Request $request, int $deliverymanId)
    {
        $dm = Deliveryman::with('user:id,name')->find($deliverymanId);
        if (!$dm) return response()->json(['success' => false, 'message' => 'Driver not found'], 404);

        $minutes = (int) $request->get('minutes', 60);
        $minutes = max(5, min($minutes, 1440)); // clamp 5 min – 24 h

        $allPoints = DB::table('driver_location_history')
            ->where('deliveryman_id', $deliverymanId)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->orderBy('created_at')
            ->select(
                DB::raw('CAST(latitude  AS DECIMAL(10,7)) as lat'),
                DB::raw('CAST(longitude AS DECIMAL(10,7)) as lng'),
                DB::raw('UNIX_TIMESTAMP(created_at) as ts'),
                DB::raw('COALESCE(speed, 0) as speed'),
                DB::raw('COALESCE(heading, 0) as heading'),
            )
            ->get();

        // Deduplicate: skip points within 15m of previous (GPS jitter at idle)
        $points = collect();
        $prev   = null;
        foreach ($allPoints as $p) {
            if (!$prev) { $points->push($p); $prev = $p; continue; }
            $dlat = ($p->lat - $prev->lat) * 111320;
            $dlng = ($p->lng - $prev->lng) * 111320 * cos(deg2rad($prev->lat));
            $dist = sqrt($dlat * $dlat + $dlng * $dlng);
            if ($dist >= 15) { $points->push($p); $prev = $p; }
        }

        return response()->json([
            'success'   => true,
            'driver_id' => $dm->id,
            'name'      => $dm->user?->name,
            'minutes'   => $minutes,
            'points'    => $points->values(),
        ]);
    }

}