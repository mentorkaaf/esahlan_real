<?php
namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
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

        // Earnings breakdown
        $earnings = Order::where('vendor_id', $vendor->id)
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'order_number', 'subtotal', 'delivery_fee', 'commission', 'total_amount', 'status', 'created_at']);

        return view('vendor.dashboard', compact('vendor', 'stats', 'recentOrders', 'chartData', 'earnings'));
    }

    public function earnings(Request $request)
    {
        $vendor = $this->activeVendor();

        $query = Order::where('vendor_id', $vendor->id)
            ->where('status', '!=', 'cancelled');

        if ($request->filled('from')) $query->whereDate('created_at', '>=', $request->from);
        if ($request->filled('to'))   $query->whereDate('created_at', '<=', $request->to);

        $orders = $query->orderByDesc('created_at')->paginate(30)->withQueryString();

        $summary = [
            'total_subtotal'   => (float) (clone $query)->sum('subtotal'),
            'total_commission' => (float) (clone $query)->sum(DB::raw('COALESCE(commission, 0)')),
            'total_delivery'   => (float) (clone $query)->sum('delivery_fee'),
            'order_count'      => (clone $query)->count(),
        ];
        $summary['total_earning'] = $summary['total_subtotal'] - $summary['total_commission'];

        return view('vendor.earnings', compact('vendor', 'orders', 'summary'));
    }

    public function toggleStore()
    {
        $vendor = $this->activeVendor();
        $vendor->update(['temporarily_closed' => !$vendor->temporarily_closed]);
        $msg = $vendor->temporarily_closed ? 'Store temporarily closed.' : 'Store is now open.';
        return back()->with('success', $msg);
    }
}
