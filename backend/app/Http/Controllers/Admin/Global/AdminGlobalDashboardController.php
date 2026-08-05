<?php

namespace App\Http\Controllers\Admin\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalOrder;
use App\Models\Global\GlobalProduct;
use App\Models\Global\GlobalUser;
use App\Models\Global\GlobalPayment;
use Illuminate\Support\Facades\DB;

class AdminGlobalDashboardController extends Controller
{
    public function index()
    {
        $today     = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        $stats = [
            'total_orders'        => GlobalOrder::count(),
            'orders_today'        => GlobalOrder::where('created_at', '>=', $today)->count(),
            'orders_this_month'   => GlobalOrder::where('created_at', '>=', $thisMonth)->count(),
            'pending_orders'      => GlobalOrder::where('status', 'pending')->count(),
            'processing_orders'   => GlobalOrder::whereIn('status', ['paid','processing'])->count(),
            'shipped_orders'      => GlobalOrder::where('status', 'shipped')->count(),

            'total_revenue'       => GlobalPayment::where('status', 'completed')->sum('amount'),
            'revenue_today'       => GlobalPayment::where('status', 'completed')->where('created_at', '>=', $today)->sum('amount'),
            'revenue_this_month'  => GlobalPayment::where('status', 'completed')->where('created_at', '>=', $thisMonth)->sum('amount'),

            'total_users'         => GlobalUser::count(),
            'users_today'         => GlobalUser::where('created_at', '>=', $today)->count(),
            'users_this_month'    => GlobalUser::where('created_at', '>=', $thisMonth)->count(),

            'total_products'      => GlobalProduct::where('is_active', true)->count(),
            'low_stock'           => GlobalProduct::where('track_stock', true)->where('stock', '<=', 5)->where('stock', '>', 0)->count(),
            'out_of_stock'        => GlobalProduct::where('track_stock', true)->where('stock', 0)->count(),
        ];

        $recentOrders = GlobalOrder::with('user')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Revenue last 7 days
        $revenueChart = GlobalPayment::where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy(DB::raw('DATE(created_at)'))
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->orderBy('date')
            ->get();

        return view('admin.global.dashboard', compact('stats', 'recentOrders', 'revenueChart'));
    }
}
