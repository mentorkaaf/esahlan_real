<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverPerformanceController extends Controller
{
    public function index()
    {
        return view('admin.drivers.performance');
    }

    public function data(Request $request)
    {
        $search = $request->search;
        $tier   = $request->tier;  // bronze|silver|gold|diamond
        $status = $request->status; // online|offline

        $drivers = DB::table('deliverymen as dm')
            ->join('users as u', 'u.id', '=', 'dm.user_id')
            ->select(
                'dm.id',
                'dm.is_active',
                'dm.earning',
                'dm.current_orders',
                'dm.avg_rating',
                'dm.vehicle_type',
                'u.name',
                'u.phone',
                'u.image',
            )
            ->when($search, fn($q) => $q->where(function($q2) use ($search) {
                $q2->where('u.name', 'like', "%{$search}%")
                   ->orWhere('u.phone', 'like', "%{$search}%");
            }))
            ->when($status === 'online', fn($q) => $q->where('dm.is_active', 1))
            ->when($status === 'offline', fn($q) => $q->where('dm.is_active', 0))
            ->orderByDesc('dm.avg_rating')
            ->limit(200)
            ->get();

        // Calculate stats per driver
        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $weekStart  = $now->copy()->startOfWeek();
        $monthStart = $now->copy()->startOfMonth();

        $driverIds = $drivers->pluck('id')->toArray();

        // Orders per driver in periods
        $ordersToday = DB::table('orders')
            ->whereIn('deliveryman_id', $driverIds)
            ->where('status', 'delivered')
            ->where('updated_at', '>=', $todayStart)
            ->select('deliveryman_id', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(delivery_fee) as earned'))
            ->groupBy('deliveryman_id')
            ->get()->keyBy('deliveryman_id');

        $ordersWeek = DB::table('orders')
            ->whereIn('deliveryman_id', $driverIds)
            ->where('status', 'delivered')
            ->where('updated_at', '>=', $weekStart)
            ->select('deliveryman_id', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(delivery_fee) as earned'))
            ->groupBy('deliveryman_id')
            ->get()->keyBy('deliveryman_id');

        $ordersMonth = DB::table('orders')
            ->whereIn('deliveryman_id', $driverIds)
            ->where('status', 'delivered')
            ->where('updated_at', '>=', $monthStart)
            ->select('deliveryman_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('deliveryman_id')
            ->get()->keyBy('deliveryman_id');

        $ordersAll = DB::table('orders')
            ->whereIn('deliveryman_id', $driverIds)
            ->where('status', 'delivered')
            ->select('deliveryman_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('deliveryman_id')
            ->get()->keyBy('deliveryman_id');

        $assignedAll = DB::table('orders')
            ->whereIn('deliveryman_id', $driverIds)
            ->whereNotIn('status', ['failed', 'pending', 'confirmed'])
            ->select('deliveryman_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('deliveryman_id')
            ->get()->keyBy('deliveryman_id');

        $result = $drivers->map(function($d) use ($ordersToday, $ordersWeek, $ordersMonth, $ordersAll, $assignedAll) {
            $totalDelivered = $ordersAll[$d->id]->cnt ?? 0;
            $totalAssigned  = $assignedAll[$d->id]->cnt ?? 0;
            $completionRate = $totalAssigned > 0 ? round(($totalDelivered / $totalAssigned) * 100) : 0;
            $rating = round((float) ($d->avg_rating ?? 0), 1);

            // Tier logic
            $tier = 'bronze';
            if ($totalDelivered >= 500 && $rating >= 4.8) $tier = 'diamond';
            elseif ($totalDelivered >= 200 && $rating >= 4.5) $tier = 'gold';
            elseif ($totalDelivered >= 50 && $rating >= 4.0) $tier = 'silver';

            return [
                'id'              => $d->id,
                'name'            => $d->name,
                'phone'           => $d->phone,
                'image'           => $d->image,
                'vehicle_type'    => $d->vehicle_type ?? null,
                'is_online'       => (bool) $d->is_active,
                'avg_rating'      => $rating,
                'tier'            => $tier,
                'total_delivered' => $totalDelivered,
                'completion_rate' => $completionRate,
                'today_orders'    => $ordersToday[$d->id]->cnt ?? 0,
                'today_earned'    => round($ordersToday[$d->id]->earned ?? 0, 2),
                'week_orders'     => $ordersWeek[$d->id]->cnt ?? 0,
                'week_earned'     => round($ordersWeek[$d->id]->earned ?? 0, 2),
                'month_orders'    => $ordersMonth[$d->id]->cnt ?? 0,
            ];
        });

        // Filter by tier after calculation
        if ($tier) {
            $result = $result->filter(fn($d) => $d['tier'] === $tier)->values();
        }

        $summary = [
            'total'   => $result->count(),
            'online'  => $result->where('is_online', true)->count(),
            'diamond' => $result->where('tier', 'diamond')->count(),
            'gold'    => $result->where('tier', 'gold')->count(),
        ];

        return response()->json(['data' => $result->values(), 'summary' => $summary]);
    }
}
