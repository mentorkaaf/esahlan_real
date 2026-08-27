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
                    'customer_name' => $o->user?->name,
                    'customer_phone'=> $o->user?->phone,
                    'customer_lat'  => (float) ($o->user?->latitude ?? 0),
                    'customer_lng'  => (float) ($o->user?->longitude ?? 0),
                    'vendor_name'   => $o->vendor?->name,
                    'vendor_lat'    => (float) ($o->vendor?->latitude ?? 0),
                    'vendor_lng'    => (float) ($o->vendor?->longitude ?? 0),
                    'vendor_logo'   => $o->vendor?->logo,
                    'driver_name'   => $o->deliveryman?->user?->name,
                    'driver_id'     => $o->deliveryman_id,
                    'placed_at'     => $o->created_at?->diffForHumans(),
                    'district'      => $addr['district'] ?? $addr['city'] ?? null,
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
            $order->update([
                'deliveryman_id' => $dm->id,
                'status'         => 'out_for_delivery',
                'dispatched_at'  => now(),
            ]);

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => 'out_for_delivery',
                'note'       => 'Dispatched to ' . ($dm->user?->name ?? 'driver'),
                'changed_by' => auth()->id(),
            ]);

            $dm->update(['status' => 'busy', 'is_available' => false]);
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

        return response()->json(['success' => true, 'message' => 'Driver assigned successfully.']);
    }

    public function liveMap()
    {
        return view('admin.dispatch.live_map');
    }

    public function liveDrivers()
    {
        $drivers = Deliveryman::with('user:id,name,phone')
            ->where('is_approved', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where(function($q) {
                // Always show online drivers (any location age)
                // Also show recently-seen drivers even if now offline (last 30 min)
                $q->where('is_online', true)
                   ->orWhere('last_location_at', '>=', now()->subMinutes(30));
            })
            ->get()
            ->map(function ($d) {
                $activeOrder = null;
                if ($d->status === 'busy') {
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
                return [
                    'id'           => $d->id,
                    'name'         => $d->user?->name ?? 'Driver #' . $d->id,
                    'phone'        => $d->user?->phone,
                    'vehicle_type' => $d->vehicle_type,
                    'status'       => $d->status,
                    'latitude'     => (float) $d->latitude,
                    'longitude'    => (float) $d->longitude,
                    'last_seen'    => \Carbon\Carbon::parse($d->last_location_at)->diffForHumans(),
                    'last_seen_at' => $d->last_location_at,
                    'is_online'    => (bool) $d->is_online,
                    'is_stale'     => $d->last_location_at && \Carbon\Carbon::parse($d->last_location_at)->diffInMinutes(now()) > 5,
                    'rating'       => round($d->rating ?? 5, 1),
                    'order'        => $activeOrder,
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

        $fcmToken = DB::table('fcm_tokens')
            ->where('user_id', $dm->user_id)
            ->orderByDesc('updated_at')
            ->value('token');

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

}