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
        $tier   = $request->tier;   // bronze|silver|gold|diamond
        $status = $request->status; // online|offline

        $drivers = DB::table('deliverymen as dm')
            ->join('users as u', 'u.id', '=', 'dm.user_id')
            ->whereNull('dm.deleted_at')
            ->where('dm.is_approved', 1)
            ->select(
                'dm.id',
                'dm.is_online',
                'dm.cash_in_hand',
                'dm.completed_orders_count',
                'dm.rating',
                'dm.vehicle_type',
                'dm.accepted_orders_count',
                'u.name',
                'u.phone',
                'u.image',
            )
            ->when($search, fn($q) => $q->where(function($q2) use ($search) {
                $q2->where('u.name', 'like', "%{$search}%")
                   ->orWhere('u.phone', 'like', "%{$search}%");
            }))
            ->when($status === 'online',  fn($q) => $q->where('dm.is_online', 1))
            ->when($status === 'offline', fn($q) => $q->where('dm.is_online', 0))
            ->orderByDesc('dm.rating')
            ->limit(200)
            ->get();

        $now        = now();
        $todayStart = $now->copy()->startOfDay();
        $weekStart  = $now->copy()->startOfWeek();
        $monthStart = $now->copy()->startOfMonth();

        $driverIds = $drivers->pluck('id')->toArray();

        if (empty($driverIds)) {
            return response()->json(['data' => [], 'summary' => ['total' => 0, 'online' => 0, 'diamond' => 0, 'gold' => 0]]);
        }

        // Time-period order stats
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

        $result = $drivers->map(function($d) use ($ordersToday, $ordersWeek, $ordersMonth) {
            // Use pre-computed columns on deliverymen table (faster than re-counting orders)
            $totalDelivered = (int) ($d->completed_orders_count ?? 0);
            $totalAccepted  = (int) ($d->accepted_orders_count ?? 0);
            $completionRate = $totalAccepted > 0 ? min(100, round(($totalDelivered / $totalAccepted) * 100)) : 0;
            $rating = round((float) ($d->rating ?? 0), 1);

            // Tier logic (same as Flutter side)
            $tier = 'bronze';
            if ($totalDelivered >= 500 && $rating >= 4.8) $tier = 'diamond';
            elseif ($totalDelivered >= 200 && $rating >= 4.5) $tier = 'gold';
            elseif ($totalDelivered >= 50  && $rating >= 4.0) $tier = 'silver';

            return [
                'id'              => $d->id,
                'name'            => $d->name ?? 'Driver #' . $d->id,
                'phone'           => $d->phone ?? '—',
                'image'           => $d->image,
                'vehicle_type'    => $d->vehicle_type ?? 'motorcycle',
                'is_online'       => (bool) $d->is_online,
                'avg_rating'      => $rating,
                'tier'            => $tier,
                'total_delivered' => $totalDelivered,
                'completion_rate' => $completionRate,
                'today_orders'    => (int) ($ordersToday[$d->id]->cnt ?? 0),
                'today_earned'    => round((float) ($ordersToday[$d->id]->earned ?? 0), 2),
                'week_orders'     => (int) ($ordersWeek[$d->id]->cnt ?? 0),
                'week_earned'     => round((float) ($ordersWeek[$d->id]->earned ?? 0), 2),
                'month_orders'    => (int) ($ordersMonth[$d->id]->cnt ?? 0),
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
