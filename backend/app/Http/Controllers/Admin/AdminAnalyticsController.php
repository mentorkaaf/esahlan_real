<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

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
        // Live metrics excluded from cache (change every minute)
        $dau            = User::whereDate('updated_at', today())->count();
        $liveRoomsToday = DB::table('live_rooms')->whereDate('created_at', today())->count();
        $liveOnline     = DB::table('live_rooms')->where('status', 'live')->count();

        $cached = Cache::remember('admin:analytics:v2', 300, function () {
            $now        = now();
            $startToday = today();
            $start7d    = $now->copy()->subDays(7);
            $start30d   = $now->copy()->subDays(30);
            $start90d   = $now->copy()->subDays(90);
            $startMonth = $now->copy()->startOfMonth();
            $start29d   = $now->copy()->subDays(29);
            $start12m   = $now->copy()->subMonths(12)->startOfMonth();
            $start13d   = $now->copy()->subDays(13);

            // ── User Metrics ─────────────────────────────────────────────────
            $totalUsers   = User::count();
            $newLast7     = User::where('created_at', '>=', $start7d)->count();
            $newLast30    = User::where('created_at', '>=', $start30d)->count();
            $newLast90    = User::where('created_at', '>=', $start90d)->count();
            $wau          = User::where('updated_at', '>=', $start7d)->count();
            $mau          = User::where('updated_at', '>=', $start30d)->count();
            $totalVendors = DB::table('vendors')->count();
            $totalDrivers = DB::table('deliverymen')->count();

            // ── Revenue — single scan with CASE WHEN ─────────────────────────
            $rev = DB::table('orders')
                ->where('status', 'delivered')
                ->selectRaw('
                    SUM(CASE WHEN DATE(created_at) = ? THEN total_amount ELSE 0 END) as today,
                    SUM(CASE WHEN created_at >= ? THEN total_amount ELSE 0 END)       as week,
                    SUM(CASE WHEN created_at >= ? THEN total_amount ELSE 0 END)       as month,
                    SUM(total_amount)                                                  as total,
                    SUM(CASE WHEN created_at >= ? THEN commission ELSE 0 END)         as commission_month,
                    SUM(commission)                                                    as commission_total
                ', [$startToday, $start7d, $startMonth, $startMonth])
                ->first();

            $revenueToday    = (float) ($rev->today ?? 0);
            $revenueWeek     = (float) ($rev->week ?? 0);
            $revenueMonth    = (float) ($rev->month ?? 0);
            $revenueTotal    = (float) ($rev->total ?? 0);
            $commissionMonth = (float) ($rev->commission_month ?? 0);
            $commissionTotal = (float) ($rev->commission_total ?? 0);

            // ── Revenue by module ────────────────────────────────────────────
            $revenueByModule = Order::where('status', 'delivered')
                ->selectRaw('COALESCE(module_slug, "ecommerce") as module, SUM(total_amount) as revenue, COUNT(*) as orders')
                ->groupBy('module')
                ->orderByDesc('revenue')
                ->get()
                ->map(fn($r) => ['module' => strtoupper($r->module), 'revenue' => (float)$r->revenue, 'orders' => (int)$r->orders]);

            // ── Daily revenue (last 30 days) ─────────────────────────────────
            $dailyRevenue = Order::where('status', 'delivered')
                ->where('created_at', '>=', $start29d)
                ->selectRaw('DATE(created_at) as date, SUM(total_amount) as revenue, COUNT(*) as orders')
                ->groupBy('date')->orderBy('date')->get()
                ->map(fn($r) => ['date' => $r->date, 'revenue' => (float)$r->revenue, 'orders' => (int)$r->orders]);

            // ── Monthly revenue (last 12 months) ─────────────────────────────
            $monthlyRevenue = Order::where('status', 'delivered')
                ->where('created_at', '>=', $start12m)
                ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(total_amount) as revenue, COUNT(*) as orders')
                ->groupBy('month')->orderBy('month')->get()
                ->map(fn($r) => ['month' => $r->month, 'revenue' => (float)$r->revenue, 'orders' => (int)$r->orders]);

            // ── User growth (last 30 days) ────────────────────────────────────
            $userGrowth = User::where('created_at', '>=', $start29d)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as new_users')
                ->groupBy('date')->orderBy('date')->get()
                ->map(fn($r) => ['date' => $r->date, 'new_users' => (int)$r->new_users]);

            // ── Community ────────────────────────────────────────────────────
            $postStats = DB::table('community_posts')
                ->selectRaw('COUNT(*) as total, SUM(created_at >= ?) as this_week', [$start7d])
                ->first();
            $totalPosts    = (int) ($postStats->total ?? 0);
            $postsThisWeek = (int) ($postStats->this_week ?? 0);

            $totalStories   = DB::table('community_stories')->count();
            $totalChats     = DB::table('community_chats')->count();
            $totalMessages  = DB::table('community_messages')->count();
            $totalFollows   = DB::table('community_follows')->count();
            $totalReactions = DB::table('community_post_reactions')->count();
            $totalComments  = DB::table('community_comments')->count();

            $communityActivity = DB::table('community_posts')
                ->where('created_at', '>=', $start13d)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as posts')
                ->groupBy('date')->orderBy('date')->get()
                ->map(fn($r) => ['date' => $r->date, 'posts' => (int)$r->posts]);

            // ── Order funnel ─────────────────────────────────────────────────
            $orderFunnel = Order::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')->pluck('count', 'status');

            $totalOrders = Order::count();

            // ── Top vendors ──────────────────────────────────────────────────
            $topVendors = DB::table('vendors')
                ->join('orders', fn($j) => $j->on('orders.vendor_id', '=', 'vendors.id')->where('orders.status', 'delivered'))
                ->selectRaw('vendors.name, SUM(orders.total_amount) as revenue, COUNT(orders.id) as orders')
                ->groupBy('vendors.id', 'vendors.name')
                ->orderByDesc('revenue')->limit(8)->get();

            // ── Retention ────────────────────────────────────────────────────
            $repeatCustomers    = DB::table(DB::raw('(SELECT user_id FROM orders GROUP BY user_id HAVING COUNT(*) > 1) t'))->count();
            $totalOrderingUsers = Order::distinct('user_id')->count('user_id');
            $retentionRate      = $totalOrderingUsers > 0 ? round($repeatCustomers / $totalOrderingUsers * 100, 1) : 0;

            // ── eMarry ───────────────────────────────────────────────────────
            $emarryStats = DB::table('emarry_profiles')
                ->selectRaw('COUNT(*) as total, SUM(status="approved") as approved, SUM(status="pending") as pending')
                ->first();
            $emarryProfiles = (int) ($emarryStats->total ?? 0);
            $emarryApproved = (int) ($emarryStats->approved ?? 0);
            $emarryPending  = (int) ($emarryStats->pending ?? 0);
            $emarryMatches  = DB::table('emarry_interests')->where('status', 'accepted')->count();
            $emarryLikes    = DB::table('emarry_interests')->count();

            // ── Live ─────────────────────────────────────────────────────────
            $liveRoomsTotal   = DB::table('live_rooms')->count();
            $liveViewersTotal = DB::table('live_room_viewers')->count();
            $giftsSent        = DB::table('gift_transactions')->count();
            $coinsIssued      = DB::table('user_coins')->sum('balance');

            // ── eLearning ────────────────────────────────────────────────────
            $elCourses      = DB::table('el_courses')->count();
            $elEnrollments  = DB::table('el_enrollments')->count();
            $elInstructors  = DB::table('el_instructors')->count();
            $elCertificates = DB::table('el_certificates')->count();

            // ── Podcast ──────────────────────────────────────────────────────
            $podcastShows    = DB::table('podcasts')->count();
            $podcastEpisodes = DB::table('podcast_episodes')->count();
            $podcastPlays    = DB::table('podcast_episode_plays')->count();
            $podcastFollows  = DB::table('podcast_follows')->count();

            // ── Crypto / Exchange ─────────────────────────────────────────────
            $cryptoOrders = DB::table('crypto_orders')->count();
            $p2pAds       = DB::table('p2p_ads')->count();

            // ── Wallet ───────────────────────────────────────────────────────
            $walletTxns   = DB::table('wallet_transactions')->count();
            $walletVolume = (float) DB::table('wallet_transactions')->where('type', 'credit')->sum('amount');

            // ── eRent ────────────────────────────────────────────────────────
            $houseRequests = DB::table('house_requests')->count();
            $rentAgents    = DB::table('users')->where('role_id', 12)->count();

            // ── Gamification ─────────────────────────────────────────────────
            $totalBadges    = DB::table('user_badges')->count();
            $totalReferrals = DB::table('referrals')->count();

            return compact(
                'totalUsers', 'newLast7', 'newLast30', 'newLast90',
                'wau', 'mau', 'totalVendors', 'totalDrivers',
                'revenueToday', 'revenueWeek', 'revenueMonth', 'revenueTotal',
                'commissionMonth', 'commissionTotal',
                'revenueByModule', 'dailyRevenue', 'monthlyRevenue', 'userGrowth',
                'totalPosts', 'postsThisWeek', 'totalStories', 'totalChats',
                'totalMessages', 'totalFollows', 'totalReactions', 'totalComments',
                'communityActivity',
                'orderFunnel', 'totalOrders', 'topVendors',
                'repeatCustomers', 'totalOrderingUsers', 'retentionRate',
                'emarryProfiles', 'emarryApproved', 'emarryPending', 'emarryMatches', 'emarryLikes',
                'liveRoomsTotal', 'liveViewersTotal', 'giftsSent', 'coinsIssued',
                'elCourses', 'elEnrollments', 'elInstructors', 'elCertificates',
                'podcastShows', 'podcastEpisodes', 'podcastPlays', 'podcastFollows',
                'cryptoOrders', 'p2pAds',
                'walletTxns', 'walletVolume',
                'houseRequests', 'rentAgents',
                'totalBadges', 'totalReferrals'
            );
        });

        return array_merge($cached, compact('dau', 'liveRoomsToday', 'liveOnline'));
    }
}
