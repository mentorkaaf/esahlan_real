<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Deliveryman;
use Illuminate\Http\Request;

class DispatchController extends Controller
{
    public function index()
    {
        $googleMapsKey = \App\Helpers\AppSettings::get('google_maps_api_key')
            ?: config('services.google.maps_api_key', '');
        $defaultLat    = \App\Helpers\AppSettings::get('google_maps_default_lat', 2.0469);
        $defaultLng    = \App\Helpers\AppSettings::get('google_maps_default_lng', 45.3182);
        $defaultZoom   = \App\Helpers\AppSettings::get('google_maps_default_zoom', 12);

        return view('admin.dispatch.index', compact('googleMapsKey', 'defaultLat', 'defaultLng', 'defaultZoom'));
    }

    public function activeOrders()
    {
        $orders = Order::with(['vendor', 'user', 'deliveryman.user'])
            ->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery'])
            ->latest()
            ->get()
            ->map(fn($o) => [
                'id'            => $o->id,
                'order_number'  => $o->order_number,
                'status'        => $o->status,
                'total'         => $o->total,
                'customer'      => $o->user?->name,
                'vendor'        => $o->vendor?->name,
                'deliveryman'   => $o->deliveryman?->user?->name,
                'delivery_lat'  => $o->delivery_latitude,
                'delivery_lng'  => $o->delivery_longitude,
                'vendor_lat'    => $o->vendor?->latitude,
                'vendor_lng'    => $o->vendor?->longitude,
                'created_at'    => $o->created_at->toISOString(),
            ]);

        return response()->json(['data' => $orders]);
    }

    public function availableDeliverymen()
    {
        $deliverymen = Deliveryman::with('user')
            ->where('status', 'available')
            ->where('is_approved', true)
            ->whereHas('user', fn($q) => $q->where('status', 'active'))
            ->get()
            ->map(fn($d) => [
                'id'        => $d->id,
                'name'      => $d->user?->name,
                'phone'     => $d->user?->phone,
                'latitude'  => $d->latitude,
                'longitude' => $d->longitude,
                'rating'    => $d->rating,
            ]);

        return response()->json(['data' => $deliverymen]);
    }

    public function manualAssign(Request $request)
    {
        $request->validate([
            'order_id'        => 'required|exists:orders,id',
            'deliveryman_id'  => 'required|exists:deliverymen,id',
        ]);

        $order = Order::findOrFail($request->order_id);
        $order->update([
            'deliveryman_id' => $request->deliveryman_id,
            'status'         => 'out_for_delivery',
            'dispatched_at'  => now(),
        ]);
        $order->updateStatus('out_for_delivery', 'Manually dispatched from Dispatch Center', auth()->id());

        return response()->json(['success' => true, 'message' => 'Deliveryman assigned.']);
    }
}
