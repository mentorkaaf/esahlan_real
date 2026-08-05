<?php

namespace App\Http\Controllers\Admin\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalShippingZone;
use App\Models\Global\GlobalOrder;
use Illuminate\Http\Request;

class AdminGlobalShippingController extends Controller
{
    public function index()
    {
        $zones = GlobalShippingZone::orderBy('id')->get();

        // Pending shipment orders
        $pendingShipment = GlobalOrder::whereIn('status', ['paid','processing'])
            ->with('user','items')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // In transit
        $inTransit = GlobalOrder::where('status', 'shipped')
            ->with('user')
            ->orderByDesc('shipped_at')
            ->limit(20)
            ->get();

        $stats = [
            'pending'   => GlobalOrder::whereIn('status', ['paid','processing'])->count(),
            'shipped'   => GlobalOrder::where('status', 'shipped')->count(),
            'delivered' => GlobalOrder::where('status', 'delivered')->count(),
            'zones'     => $zones->count(),
        ];

        return view('admin.global.shipping.index', compact('zones','pendingShipment','inTransit','stats'));
    }

    public function storeZone(Request $request)
    {
        $data = $request->validate([
            'name'               => 'required|string',
            'countries'          => 'required|string',
            'flat_rate'          => 'required|numeric|min:0',
            'free_shipping_over' => 'nullable|numeric|min:0',
            'estimated_days_min' => 'required|integer|min:1',
            'estimated_days_max' => 'required|integer|min:1',
        ]);
        $data['countries'] = array_map('trim', explode(',', $data['countries']));
        $data['is_active']  = true;
        GlobalShippingZone::create($data);
        return back()->with('success', 'Shipping zone created.');
    }

    public function updateZone(Request $request, GlobalShippingZone $zone)
    {
        $data = $request->validate([
            'name'               => 'required|string',
            'countries'          => 'required|string',
            'flat_rate'          => 'required|numeric|min:0',
            'free_shipping_over' => 'nullable|numeric|min:0',
            'estimated_days_min' => 'required|integer|min:1',
            'estimated_days_max' => 'required|integer|min:1',
            'is_active'          => 'boolean',
        ]);
        $data['countries'] = array_map('trim', explode(',', $data['countries']));
        $data['is_active']  = $request->boolean('is_active', true);
        $zone->update($data);
        return back()->with('success', 'Zone updated.');
    }

    public function destroyZone(GlobalShippingZone $zone)
    {
        $zone->delete();
        return back()->with('success', 'Zone deleted.');
    }
}
