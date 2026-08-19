<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use App\Models\EGrocery\EGroceryOrder;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', $this->getStats());
    }

    public function stats()
    {
        return response()->json(['success' => true, 'data' => $this->getStats()]);
    }

    private function getStats(): array
    {
        $today     = now()->startOfDay();
        $thisMonth = now()->startOfMonth();
        $last30    = now()->subDays(30);
        $last7     = now()->subDays(7);

        // ── Core stats ─────────────────────────────────────────────────
        $totalUsers    = User::count();
        $todayUsers    = User::whereDate('created_at', today())->count();
        $monthlyUsers  = User::where('created_at', '>=', $thisMonth)->count();
        $activeUsers   = User::where('updated_at', '>=', $last30)->count();

        $totalOrders   = Order::count();
        $todayOrders   = Order::whereDate('created_at', today())->count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $deliveredOrders = Order::where('status', 'delivered')->count();
        $cancelledOrders = Order::where('status', 'cancelled')->count();

        $totalRevenue   = Order::where('status', 'delivered')->sum('total_amount');
        $todayRevenue   = Order::where('status', 'delivered')->whereDate('created_at', today())->sum('total_amount');
        $monthlyRevenue = Order::where('status', 'delivered')->where('created_at', '>=', $thisMonth)->sum('total_amount');

        // ── eGrocery (separate table — not yet in orders; avoid double-count after mirror) ──
        $mirroredEgNos = Order::where('module_slug', 'egrocery')->pluck('order_number')->toArray();
        $egQ = EGroceryOrder::whereNotIn('order_no', $mirroredEgNos);
        $totalOrders    += (clone $egQ)->count();
        $todayOrders    += (clone $egQ)->whereDate('created_at', today())->count();
        $pendingOrders  += (clone $egQ)->where('status', 'pending')->count();
        $deliveredOrders+= (clone $egQ)->where('status', 'delivered')->count();
        $cancelledOrders+= (clone $egQ)->where('status', 'cancelled')->count();
        $totalRevenue   += (float)(clone $egQ)->where('status', 'delivered')->sum('total');
        $todayRevenue   += (float)(clone $egQ)->where('status', 'delivered')->whereDate('created_at', today())->sum('total');
        $monthlyRevenue += (float)(clone $egQ)->where('status', 'delivered')->where('created_at', '>=', $thisMonth)->sum('total');

        $totalCommission   = Order::where('status', 'delivered')->sum('commission');
        $monthlyCommission = Order::where('status', 'delivered')->where('created_at', '>=', $thisMonth)->sum('commission');

        // ── Vendors ─────────────────────────────────────────────────────
        $totalVendors   = Vendor::count();
        $activeVendors  = Vendor::where('is_active', true)->count();
        $pendingVendors = Vendor::where('is_approved', false)->count();

        // ── Deliverymen ─────────────────────────────────────────────────
        $totalDelivery     = DB::table('deliverymen')->count();
        $availableDelivery = DB::table('deliverymen')->where('status', 'available')->count();
        $busyDelivery      = DB::table('deliverymen')->where('status', 'busy')->count();

        // ── Withdrawals ─────────────────────────────────────────────────
        $pendingWithdrawals = DB::table('withdrawal_requests')->where('status', 'pending')->count();
        $totalWithdrawals   = DB::table('withdrawal_requests')->where('status', 'approved')->sum('amount');

        // ── Wallet ──────────────────────────────────────────────────────
        $totalWalletBalance = DB::table('wallets')->sum('balance');
        $walletCount        = DB::table('wallets')->where('balance', '>', 0)->count();

        // ── Products ────────────────────────────────────────────────────
        $totalProducts  = DB::table('products')->count();
        $activeProducts = DB::table('products')->where('is_available', true)->count();

        // ── Reviews ─────────────────────────────────────────────────────
        $totalReviews  = DB::table('reviews')->count();
        $avgRating     = round(DB::table('reviews')->avg('rating') ?? 0, 1);

        // ── Community ───────────────────────────────────────────────────
        $communityPosts    = DB::table('community_posts')->count();
        $communityMembers  = DB::table('community_profiles')->count();
        $communityGroups   = DB::table('community_groups')->count();
        $communityStories  = DB::table('community_stories')->count();
        $communityReports  = DB::table('community_reports')->where('status', 'pending')->count();
        $communityComments = DB::table('community_comments')->count();
        $communityFollows  = DB::table('community_follows')->count();

        // ── Notifications ───────────────────────────────────────────────
        $totalNotifications = DB::table('push_notification_logs')->count();
        $sentNotifications  = DB::table('push_notification_logs')->where('sent_count', '>', 0)->sum('sent_count');

        // ── Coupons ─────────────────────────────────────────────────────
        $totalCoupons  = DB::table('coupons')->count();
        $couponUsages  = DB::table('coupon_usages')->count();

        // ── Module revenue breakdown ─────────────────────────────────────
        $moduleRevenue = Order::where('status', 'delivered')
            ->selectRaw('COALESCE(module_slug, "other") as module, SUM(total_amount) as revenue, COUNT(*) as count')
            ->groupBy('module')
            ->orderByDesc('revenue')
            ->get();

        // Add unmirrored eGrocery revenue to module breakdown
        $egRevenue = (float) EGroceryOrder::whereNotIn('order_no', $mirroredEgNos)->where('status', 'delivered')->sum('total');
        $egCount   = EGroceryOrder::whereNotIn('order_no', $mirroredEgNos)->where('status', 'delivered')->count();
        if ($egRevenue > 0 || $egCount > 0) {
            $existing = $moduleRevenue->firstWhere('module', 'egrocery');
            if ($existing) {
                $existing->revenue += $egRevenue;
                $existing->count   += $egCount;
            } else {
                $moduleRevenue->push((object)['module' => 'egrocery', 'revenue' => $egRevenue, 'count' => $egCount]);
            }
        }

        // ── Order status breakdown ────────────────────────────────────────
        $orderStatusBreakdown = Order::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // ── Top vendors by revenue ────────────────────────────────────────
        $topVendors = Vendor::select('vendors.id', 'vendors.name', 'vendors.logo')
            ->selectRaw('SUM(orders.total_amount) as total_revenue, COUNT(orders.id) as order_count')
            ->join('orders', function ($j) {
                $j->on('orders.vendor_id', '=', 'vendors.id')
                  ->where('orders.status', 'delivered');
            })
            ->groupBy('vendors.id', 'vendors.name', 'vendors.logo')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        // ── Daily orders/revenue last 30 days ────────────────────────────
        $dailyOrders = Order::selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(total_amount) as revenue')
            ->where('created_at', '>=', $last30)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // ── User growth last 14 days ─────────────────────────────────────
        $userGrowth = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(14))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // ── Revenue by month (last 6 months) ─────────────────────────────
        $monthlyRevenueChart = Order::where('status', 'delivered')
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(total_amount) as revenue, COUNT(*) as orders')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // ── Recent activity (last 10 significant events) ─────────────────
        $recentOrders = Order::with(['user:id,name'])
            ->latest()->limit(8)->get();

        return compact(
            'totalUsers', 'todayUsers', 'monthlyUsers', 'activeUsers',
            'totalOrders', 'todayOrders', 'pendingOrders', 'deliveredOrders', 'cancelledOrders',
            'totalRevenue', 'todayRevenue', 'monthlyRevenue',
            'totalCommission', 'monthlyCommission',
            'totalVendors', 'activeVendors', 'pendingVendors',
            'totalDelivery', 'availableDelivery', 'busyDelivery',
            'pendingWithdrawals', 'totalWithdrawals',
            'totalWalletBalance', 'walletCount',
            'totalProducts', 'activeProducts',
            'totalReviews', 'avgRating',
            'communityPosts', 'communityMembers', 'communityGroups', 'communityStories',
            'communityReports', 'communityComments', 'communityFollows',
            'totalNotifications', 'sentNotifications',
            'totalCoupons', 'couponUsages',
            'moduleRevenue', 'orderStatusBreakdown',
            'topVendors', 'dailyOrders', 'userGrowth', 'monthlyRevenueChart',
            'recentOrders'
        );
    }
}
