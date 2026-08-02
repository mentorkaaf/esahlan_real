<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsController extends Controller
{
    public function index()
    {
        return view('admin.analytics.index', $this->getData());
    }

    public function api()
    {
        return response()->json(['success' => true, 'data' => $this->getData()]);
    }

    private function getData(): array
    {
        $now = now();

        // ── User Metrics ────────────────────────────────────────────────────
        $totalUsers  = User::count();
        $newLast7    = User::where('created_at', '>=', $now->copy()->subDays(7))->count();
        $newLast30   = User::where('created_at', '>=', $now->copy()->subDays(30))->count();
        $newLast90   = User::where('created_at', '>=', $now->copy()->subDays(90))->count();

        // DAU — users who made any API request (updated_at proxy)
        $dau = User::whereDate('updated_at', today())->count();
        // WAU
        $wau = User::where('updated_at', '>=', $now->copy()->subDays(7))->count();
        // MAU
        $mau = User::where('updated_at', '>=', $now->copy()->subDays(30))->count();

        // ── Revenue ─────────────────────────────────────────────────────────
        $revenueToday   = Order::where('status', 'delivered')->whereDate('created_at', today())->sum('total_amount');
        $revenueWeek    = Order::where('status', 'delivered')->where('created_at', '>=', $now->copy()->subDays(7))->sum('total_amount');
        $revenueMonth   = Order::where('status', 'delivered')->where('created_at', '>=', $now->copy()->startOfMonth())->sum('total_amount');
        $revenueTotal   = Order::where('status', 'delivered')->sum('total_amount');

        $commissionMonth = Order::where('status', 'delivered')->where('created_at', '>=', $now->copy()->startOfMonth())->sum('commission');
        $commissionTotal = Order::where('status', 'delivered')->sum('commission');

        // ── Revenue by module ────────────────────────────────────────────────
        $revenueByModule = Order::where('status', 'delivered')
            ->selectRaw('COALESCE(module_slug, "ecommerce") as module, SUM(total_amount) as revenue, COUNT(*) as orders')
            ->groupBy('module')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn($r) => ['module' => strtoupper($r->module), 'revenue' => (float)$r->revenue, 'orders' => (int)$r->orders]);

        // ── Daily revenue + orders (last 30 days) ────────────────────────────
        $dailyRevenue = Order::where('status', 'delivered')
            ->where('created_at', '>=', $now->copy()->subDays(29))
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as revenue, COUNT(*) as orders')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($r) => ['date' => $r->date, 'revenue' => (float)$r->revenue, 'orders' => (int)$r->orders]);

        // ── Monthly revenue (last 12 months) ────────────────────────────────
        $monthlyRevenue = Order::where('status', 'delivered')
            ->where('created_at', '>=', $now->copy()->subMonths(12)->startOfMonth())
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(total_amount) as revenue, COUNT(*) as orders')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn($r) => ['month' => $r->month, 'revenue' => (float)$r->revenue, 'orders' => (int)$r->orders]);

        // ── User growth daily (last 30 days) ─────────────────────────────────
        $userGrowth = User::where('created_at', '>=', $now->copy()->subDays(29))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as new_users')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($r) => ['date' => $r->date, 'new_users' => (int)$r->new_users]);

        // ── Community engagement ──────────────────────────────────────────────
        $totalPosts    = DB::table('community_posts')->count();
        $postsThisWeek = DB::table('community_posts')->where('created_at', '>=', $now->copy()->subDays(7))->count();
        $totalStories  = DB::table('community_stories')->count();
        $totalChats    = DB::table('community_chats')->count();
        $totalMessages = DB::table('community_messages')->count();
        $totalFollows  = DB::table('community_follows')->count();
        $totalReactions= DB::table('community_post_reactions')->count();

        // Community daily activity (last 14 days)
        $communityActivity = DB::table('community_posts')
            ->where('created_at', '>=', $now->copy()->subDays(13))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as posts')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($r) => ['date' => $r->date, 'posts' => (int)$r->posts]);

        // ── Order funnel ──────────────────────────────────────────────────────
        $orderFunnel = Order::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // ── Top vendors ────────────────────────────────────────────────────────
        $topVendors = DB::table('vendors')
            ->join('orders', function($j) {
                $j->on('orders.vendor_id', '=', 'vendors.id')->where('orders.status', 'delivered');
            })
            ->selectRaw('vendors.name, SUM(orders.total_amount) as revenue, COUNT(orders.id) as orders')
            ->groupBy('vendors.id', 'vendors.name')
            ->orderByDesc('revenue')
            ->limit(8)
            ->get();

        // ── Retention proxy: users who ordered > once ────────────────────────
        $repeatCustomers = Order::selectRaw('user_id, COUNT(*) as order_count')
            ->groupBy('user_id')
            ->havingRaw('order_count > 1')
            ->get()->count();
        $totalOrderingUsers = Order::distinct('user_id')->count('user_id');
        $retentionRate = $totalOrderingUsers > 0 ? round($repeatCustomers / $totalOrderingUsers * 100, 1) : 0;

        // ── eMarry stats ────────────────────────────────────────────────────
        $emarryProfiles = DB::table('emarry_profiles')->count();
        $emarryApproved = DB::table('emarry_profiles')->where('status', 'approved')->count();
        $emarryMatches  = DB::table('emarry_interests')
            ->where('status', 'accepted')
            ->count();

        // ── Live streaming stats ─────────────────────────────────────────────
        $liveRoomsTotal = DB::table('live_rooms')->count();
        $liveRoomsToday = DB::table('live_rooms')->whereDate('created_at', today())->count();

        return compact(
            'totalUsers', 'newLast7', 'newLast30', 'newLast90',
            'dau', 'wau', 'mau',
            'revenueToday', 'revenueWeek', 'revenueMonth', 'revenueTotal',
            'commissionMonth', 'commissionTotal',
            'revenueByModule', 'dailyRevenue', 'monthlyRevenue', 'userGrowth',
            'totalPosts', 'postsThisWeek', 'totalStories', 'totalChats',
            'totalMessages', 'totalFollows', 'totalReactions', 'communityActivity',
            'orderFunnel', 'topVendors',
            'repeatCustomers', 'totalOrderingUsers', 'retentionRate',
            'emarryProfiles', 'emarryApproved', 'emarryMatches',
            'liveRoomsTotal', 'liveRoomsToday'
        );
    }
}
