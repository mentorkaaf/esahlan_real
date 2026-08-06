<?php

namespace App\Http\Controllers\Admin\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalOrder;
use App\Models\Global\GlobalUser;
use App\Models\Global\GlobalProduct;
use App\Models\Global\GlobalPayment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminGlobalAnalyticsController extends Controller
{
    public function index()
    {
        $now   = now();
        $days  = 30;

        // Daily orders — last 30 days
        $dailyOrders = GlobalOrder::where('created_at', '>=', $now->copy()->subDays($days - 1)->startOfDay())
            ->groupBy(DB::raw('DATE(created_at)'))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total) as revenue')
            ->orderBy('date')
            ->get();

        // Top products by revenue
        $topProducts = DB::table('global_order_items')
            ->join('global_products', 'global_order_items.global_product_id', '=', 'global_products.id')
            ->select('global_products.name', 'global_products.thumbnail',
                DB::raw('SUM(global_order_items.quantity) as units'),
                DB::raw('SUM(global_order_items.total_price) as revenue'))
            ->groupBy('global_order_items.global_product_id', 'global_products.name', 'global_products.thumbnail')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        // Orders by country
        $byCountry = GlobalOrder::groupBy('shipping_country')
            ->selectRaw('shipping_country, COUNT(*) as orders, SUM(total) as revenue')
            ->orderByDesc('orders')
            ->limit(10)
            ->get();

        // Conversion funnel (users vs orders)
        $totalUsers       = GlobalUser::count();
        $usersWithOrders  = GlobalOrder::distinct('global_user_id')->count('global_user_id');
        $conversionRate   = $totalUsers > 0 ? round($usersWithOrders / $totalUsers * 100, 1) : 0;

        // Order status breakdown
        $statusBreakdown = GlobalOrder::groupBy('status')
            ->selectRaw('status, COUNT(*) as count')
            ->pluck('count', 'status');

        // AOV (Average Order Value)
        $aov = GlobalOrder::where('status', '!=', 'cancelled')->avg('total') ?? 0;

        // New users last 30 days
        $newUsers = GlobalUser::where('created_at', '>=', $now->copy()->subDays($days - 1)->startOfDay())
            ->groupBy(DB::raw('DATE(created_at)'))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as users')
            ->orderBy('date')
            ->get();

        return view('admin.global.analytics.index', compact(
            'dailyOrders','topProducts','byCountry','totalUsers',
            'usersWithOrders','conversionRate','statusBreakdown','aov','newUsers'
        ));
    }
}
