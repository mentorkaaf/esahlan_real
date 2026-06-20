<?php
namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class VendorDashboardWebController extends Controller
{
    use HasActiveVendor;

    public function index()
    {
        $vendor = $this->activeVendor();
        $today  = now()->toDateString();

        $baseQuery = fn() => Order::where('vendor_id', $vendor->id)->where('status', '!=', 'cancelled');

        $todayEarning = (clone $baseQuery())->whereDate('created_at', $today)
            ->selectRaw('COALESCE(SUM(subtotal - COALESCE(commission, 0)), 0) as earning')
            ->value('earning');

        $monthEarning = (clone $baseQuery())->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)
            ->selectRaw('COALESCE(SUM(subtotal - COALESCE(commission, 0)), 0) as earning')
            ->value('earning');

        $totalCommission = (clone $baseQuery())
            ->selectRaw('COALESCE(SUM(COALESCE(commission, 0)), 0) as total')
            ->value('total');

        $stats = [
            'today_orders'    => Order::where('vendor_id', $vendor->id)->whereDate('created_at', $today)->count(),
            'today_earning'   => (float) $todayEarning,
            'pending_orders'  => Order::where('vendor_id', $vendor->id)->where('status', 'pending')->count(),
            'total_orders'    => Order::where('vendor_id', $vendor->id)->count(),
            'this_month'      => (float) $monthEarning,
            'total_commission'=> (float) $totalCommission,
            'rating'          => round($vendor->rating ?? 0, 1),
            'total_reviews'   => $vendor->review_count ?? 0,
        ];

        $recentOrders = Order::with(['user', 'items'])
            ->where('vendor_id', $vendor->id)
            ->latest()
            ->limit(10)
            ->get();

        $chartData = Order::where('vendor_id', $vendor->id)
            ->where('status', '!=', 'cancelled')
            ->whereDate('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, COALESCE(SUM(subtotal - COALESCE(commission, 0)), 0) as revenue')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return view('vendor.dashboard', compact('vendor', 'stats', 'recentOrders', 'chartData'));
    }

    public function toggleStore()
    {
        $vendor = $this->activeVendor();
        $vendor->update(['temporarily_closed' => !$vendor->temporarily_closed]);
        $msg = $vendor->temporarily_closed ? 'Store temporarily closed.' : 'Store is now open.';
        return back()->with('success', $msg);
    }
}
