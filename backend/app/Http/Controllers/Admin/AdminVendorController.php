<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminVendorController extends Controller
{
    public function index(Request $request)
    {
        $query = Vendor::with(['module', 'district', 'user'])
            ->when($request->module_id, fn($q) => $q->where('module_id', $request->module_id))
            ->when($request->approved, fn($q) => $q->where('is_approved', $request->approved === '1'))
            ->when($request->search, fn($q) => $q->where(function ($s) use ($request) {
                $s->where('name', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%");
            }))
            ->latest();

        $vendors = $query->paginate(20);
        $modules = Module::where('is_active', true)->get();

        return view('admin.vendors.index', compact('vendors', 'modules'));
    }

    public function show(Vendor $vendor)
    {
        $vendor->load(['module', 'district', 'user', 'schedules']);

        $vid = $vendor->id;

        // Orders stats
        $orderStats = DB::table('orders')->where('vendor_id', $vid)->selectRaw("
            COUNT(*) as total,
            SUM(total_amount) as revenue,
            SUM(CASE WHEN status='delivered' THEN total_amount ELSE 0 END) as delivered_revenue,
            SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN status='pending'   THEN 1 ELSE 0 END) as pending,
            AVG(total_amount) as avg_order,
            COUNT(DISTINCT user_id) as unique_customers
        ")->first();

        // This month vs last month
        $thisMonth = DB::table('orders')->where('vendor_id', $vid)
            ->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)
            ->selectRaw('COUNT(*) as orders, SUM(total_amount) as revenue')->first();
        $lastMonth = DB::table('orders')->where('vendor_id', $vid)
            ->whereMonth('created_at', now()->subMonth()->month)->whereYear('created_at', now()->subMonth()->year)
            ->selectRaw('COUNT(*) as orders, SUM(total_amount) as revenue')->first();

        // Last 30 days daily chart
        $chart = DB::table('orders')->where('vendor_id', $vid)
            ->where('created_at', '>=', now()->subDays(29))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total_amount) as revenue')
            ->groupBy(DB::raw('DATE(created_at)'))->orderBy('date')->get();

        // Commission — prefer commissions table, fall back to calculating from orders + vendor rate
        $commissionStats = DB::table('commissions')->where('vendor_id', $vid)
            ->selectRaw('SUM(commission_amount) as total_commission, SUM(vendor_earning) as total_earning')->first();
        if (!$commissionStats || (!$commissionStats->total_commission && !$commissionStats->total_earning)) {
            $rate = floatval($vendor->commission_value ?? 10) / 100;
            $totalRev = floatval($orderStats->delivered_revenue ?? 0);
            $commissionStats = (object)[
                'total_commission' => round($totalRev * $rate, 2),
                'total_earning'    => round($totalRev * (1 - $rate), 2),
            ];
        }

        // Top products
        $topProducts = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.vendor_id', $vid)
            ->selectRaw('products.id, products.name, products.thumbnail, COUNT(*) as sold, SUM(order_items.total) as revenue')
            ->groupBy('products.id', 'products.name', 'products.thumbnail')
            ->orderByDesc('sold')->limit(5)->get();

        // Products count
        $productCount = DB::table('products')->where('vendor_id', $vid)->count();

        // Recent orders
        $recentOrders = DB::table('orders')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->where('orders.vendor_id', $vid)
            ->select('orders.*', 'users.name as customer_name')
            ->latest('orders.created_at')->limit(10)->get();

        // Status breakdown for donut
        $statusBreakdown = DB::table('orders')->where('vendor_id', $vid)
            ->selectRaw('status, COUNT(*) as cnt')->groupBy('status')->get();

        return view('admin.vendors.show', compact(
            'vendor', 'orderStats', 'thisMonth', 'lastMonth',
            'chart', 'commissionStats', 'topProducts', 'productCount',
            'recentOrders', 'statusBreakdown'
        ));
    }

    public function approve(Vendor $vendor)
    {
        $vendor->update(['is_approved' => true, 'is_active' => true, 'status' => 'active']);
        return back()->with('success', 'Vendor approved successfully.');
    }

    public function reject(Request $request, Vendor $vendor)
    {
        $vendor->update(['is_approved' => false, 'is_active' => false, 'status' => 'suspended']);
        return back()->with('success', 'Vendor rejected.');
    }

    public function toggleFeatured(Vendor $vendor)
    {
        $vendor->update(['is_featured' => !$vendor->is_featured]);
        return back()->with('success', 'Featured status updated.');
    }

    public function destroy(Vendor $vendor)
    {
        $vendor->delete();
        return redirect()->route('admin.vendors.index')->with('success', 'Vendor deleted.');
    }
}
