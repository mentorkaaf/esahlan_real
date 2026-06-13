<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = $this->getStats();
        return view('admin.dashboard', compact('stats'));
    }

    public function stats()
    {
        return response()->json(['success' => true, 'data' => $this->getStats()]);
    }

    private function getStats(): array
    {
        $today     = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        return [
            'users' => [
                'total'   => User::count(),
                'today'   => User::whereDate('created_at', today())->count(),
                'monthly' => User::where('created_at', '>=', $thisMonth)->count(),
            ],
            'orders' => [
                'total'     => Order::count(),
                'today'     => Order::whereDate('created_at', today())->count(),
                'pending'   => Order::where('status', 'pending')->count(),
                'delivered' => Order::where('status', 'delivered')->count(),
            ],
            'revenue' => [
                'total'   => Order::where('status', 'delivered')->sum('total_amount'),
                'today'   => Order::where('status', 'delivered')->whereDate('created_at', today())->sum('total_amount'),
                'monthly' => Order::where('status', 'delivered')->where('created_at', '>=', $thisMonth)->sum('total_amount'),
            ],
            'commission' => [
                'total'   => Order::where('status', 'delivered')->sum('commission'),
                'monthly' => Order::where('status', 'delivered')->where('created_at', '>=', $thisMonth)->sum('commission'),
            ],
            'vendors' => [
                'total'    => Vendor::count(),
                'active'   => Vendor::where('is_active', true)->count(),
                'pending'  => Vendor::where('is_approved', false)->count(),
            ],
            'deliverymen' => [
                'total'     => DB::table('deliverymen')->count(),
                'available' => DB::table('deliverymen')->where('status', 'available')->count(),
                'busy'      => DB::table('deliverymen')->where('status', 'busy')->count(),
            ],
            'withdrawals' => [
                'pending' => DB::table('withdrawal_requests')->where('status', 'pending')->count(),
                'total'   => DB::table('withdrawal_requests')->where('status', 'approved')->sum('amount'),
            ],
            'chart' => [
                'daily_orders' => Order::selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(total_amount) as revenue')
                    ->where('created_at', '>=', now()->subDays(30))
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get(),
            ],
        ];
    }
}
