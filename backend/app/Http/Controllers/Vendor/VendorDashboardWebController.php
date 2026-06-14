<?php
namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class VendorDashboardWebController extends Controller
{
    public function index()
    {
        $vendor = auth()->user()->vendor;
        $today  = now()->toDateString();

        $stats = [
            'today_orders'   => Order::where('vendor_id', $vendor->id)->whereDate('created_at', $today)->count(),
            'today_revenue'  => Order::where('vendor_id', $vendor->id)->whereDate('created_at', $today)
                ->where('status', '!=', 'cancelled')->sum('total'),
            'pending_orders' => Order::where('vendor_id', $vendor->id)->where('status', 'pending')->count(),
            'total_orders'   => Order::where('vendor_id', $vendor->id)->count(),
            'this_month'     => Order::where('vendor_id', $vendor->id)
                ->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)
                ->where('status', '!=', 'cancelled')->sum('total'),
            'rating'         => round($vendor->rating ?? 0, 1),
            'total_reviews'  => $vendor->review_count ?? 0,
        ];

        $recentOrders = Order::with(['user', 'items'])
            ->where('vendor_id', $vendor->id)
            ->latest()
            ->limit(10)
            ->get();

        $chartData = Order::where('vendor_id', $vendor->id)
            ->where('status', '!=', 'cancelled')
            ->whereDate('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total) as revenue')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return view('vendor.dashboard', compact('vendor', 'stats', 'recentOrders', 'chartData'));
    }

    public function toggleStore()
    {
        $vendor = auth()->user()->vendor;
        $vendor->update(['temporarily_closed' => !$vendor->temporarily_closed]);
        $msg = $vendor->temporarily_closed ? 'Mağaza geçici olarak kapatıldı.' : 'Mağaza açıldı.';
        return back()->with('success', $msg);
    }
}
