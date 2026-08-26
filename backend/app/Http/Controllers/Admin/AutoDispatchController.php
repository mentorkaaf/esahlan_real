<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Http\Controllers\Api\Delivery\DeliveryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AutoDispatchController extends Controller {
    public function dispatch(Request $request, Order $order) {
        $order->load(['vendor:id,name,latitude,longitude,district_id',
            'vendor.district:id,name,latitude,longitude','user:id,name,phone']);
        $dispatched = DeliveryController::autoDispatch($order);
        if ($dispatched) return response()->json(['success'=>true,'message'=>'Auto-dispatch sent to nearest driver']);
        return response()->json(['success'=>false,'message'=>'No available drivers in range'],422);
    }

    public function nearestDrivers(Request $request, Order $order) {
        $order->load(['vendor:id,latitude,longitude']);
        $pLat=(float)($order->vendor?->latitude??0);
        $pLng=(float)($order->vendor?->longitude??0);
        $drivers = \App\Models\Deliveryman::where('is_online',true)
            ->where('is_approved',true)->whereNotNull('latitude')
            ->where('last_location_at','>=',now()->subMinutes(15))
            ->with('user:id,name,phone')->get()
            ->map(function($dm) use ($pLat,$pLng) {
                $lat=(float)$dm->latitude; $lng=(float)$dm->longitude;
                $dist=($lat&&$lng&&$pLat)?round(6371*acos(min(1,
                    cos(deg2rad($pLat))*cos(deg2rad($lat))*cos(deg2rad($lng)-deg2rad($pLng))
                    +sin(deg2rad($pLat))*sin(deg2rad($lat)))),1):null;
                return ['id'=>$dm->id,'name'=>$dm->user?->name,'phone'=>$dm->user?->phone,
                    'rating'=>round($dm->rating??5.0,1),'vehicle_type'=>$dm->vehicle_type,
                    'status'=>$dm->status,'distance_km'=>$dist];
            })->sortBy('distance_km')->values();
        return response()->json(['success'=>true,'data'=>$drivers]);
    }
}
