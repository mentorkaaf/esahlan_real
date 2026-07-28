<?php
namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $vendor = auth()->user()->vendor;

        if (!$vendor) {
            return $this->error('No vendor profile found.', 404);
        }

        $today = now()->toDateString();

        $stats = [
            'today_orders'    => Order::where('vendor_id', $vendor->id)->whereDate('created_at', $today)->count(),
            'today_revenue'   => Order::where('vendor_id', $vendor->id)->whereDate('created_at', $today)
                ->where('status', '!=', 'cancelled')->sum('total_amount'),
            'pending_orders'  => Order::where('vendor_id', $vendor->id)->where('status', 'pending')->count(),
            'total_orders'    => Order::where('vendor_id', $vendor->id)->count(),
            'this_month'      => Order::where('vendor_id', $vendor->id)
                ->whereMonth('created_at', now()->month)
                ->where('status', '!=', 'cancelled')->sum('total_amount'),
            'rating'          => round($vendor->rating, 1),
            'total_reviews'   => $vendor->review_count ?? $vendor->total_reviews ?? 0,
        ];

        $recentOrders = Order::with(['user', 'items'])
            ->where('vendor_id', $vendor->id)
            ->latest()
            ->limit(10)
            ->get();

        $chartData = Order::where('vendor_id', $vendor->id)
            ->where('status', '!=', 'cancelled')
            ->whereDate('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total_amount) as revenue')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return $this->success([
            'vendor'        => [
                'id'                   => $vendor->id,
                'name'                 => $vendor->name,
                'logo'                 => $vendor->logo_url,
                'module_slug'          => $vendor->module_slug,
                'is_open'              => $vendor->isCurrentlyOpen(),
                'temporarily_closed'   => $vendor->temporarily_closed,
                'rating'               => round($vendor->rating, 1),
            ],
            'stats'         => $stats,
            'recent_orders' => $recentOrders,
            'chart_data'    => $chartData,
        ]);
    }

    public function toggleStore(): JsonResponse
    {
        $vendor = auth()->user()->vendor;
        $vendor->update(['temporarily_closed' => !$vendor->temporarily_closed]);
        return $this->success([
            'temporarily_closed' => $vendor->temporarily_closed,
            'message' => $vendor->temporarily_closed ? 'Store closed temporarily.' : 'Store is now open.',
        ]);
    }

    public function updateFcmToken(Request $request): JsonResponse
    {
        $request->validate(['fcm_token' => 'nullable|string']);
        $vendor = auth()->user()->vendor;
        $token = $request->input('fcm_token') ?: null;
        $vendor->update(['vendor_fcm_token' => $token]);
        return $this->success(['message' => 'FCM token updated']);
    }

    public function testNotification(): JsonResponse
    {
        $vendor = auth()->user()->vendor;
        $token  = $vendor?->vendor_fcm_token;

        if (!$token) {
            return $this->error('No FCM token. Logout and login again to register.', 422);
        }

        $ok = \App\Services\FcmService::sendToToken(
            $token,
            '🔔 Test Notification',
            'FCM is working! You will receive order notifications.',
            ['type' => 'test', 'deep_link' => '/orders']
        );

        return $this->success([
            'sent'          => $ok,
            'token_preview' => '...' . substr($token, -20),
        ]);
    }
}
