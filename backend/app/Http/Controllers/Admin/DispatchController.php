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

        // Notify driver (correct driver template)
        try {
            $driverToken = $dm->fcm_token ?? $dm->user?->fcm_token;
            if ($driverToken) {
                \App\Services\FcmService::sendDriverOrderUpdate(
                    $driverToken,
                    $order->order_number, 'out_for_delivery', $order->id, $order->module_slug
                );
            }
        } catch (\Throwable) {}

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
            ->where('last_location_at', '>=', now()->subMinutes(30))
            ->get()
            ->map(function ($d) {
                $activeOrder = null;
                if ($d->status === 'busy') {
                    $activeOrder = DB::table('orders')
                        ->where('deliveryman_id', $d->id)
                        ->whereIn('status', ['out_for_delivery', 'ready_for_pickup'])
                        ->select('id', 'order_number', 'status', 'module_slug')
                        ->first();
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
                    'rating'       => round($d->rating ?? 5, 1),
                    'order'        => $activeOrder,
                ];
            });

        return response()->json(['data' => $drivers, 'updated_at' => now()->toISOString()]);
    }
}
