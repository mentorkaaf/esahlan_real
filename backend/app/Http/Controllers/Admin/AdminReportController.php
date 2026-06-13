<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReportController extends Controller
{
    public function salesReport(Request $request)
    {
        $dateFrom = $request->from ?? now()->startOfMonth()->toDateString();
        $dateTo   = $request->to   ?? now()->toDateString();

        $summary = Order::whereBetween(DB::raw('DATE(created_at)'), [$dateFrom, $dateTo])
            ->where('status', '!=', 'cancelled')
            ->selectRaw('
                COUNT(*) as total_orders,
                SUM(total_amount) as total_revenue,
                SUM(commission) as total_commission,
                AVG(total_amount) as avg_order_value
            ')
            ->first();

        $byModule = Order::whereBetween(DB::raw('DATE(created_at)'), [$dateFrom, $dateTo])
            ->where('status', '!=', 'cancelled')
            ->selectRaw('module_slug, COUNT(*) as orders, SUM(total_amount) as revenue, SUM(commission) as commission')
            ->groupBy('module_slug')
            ->get();

        $daily = Order::whereBetween(DB::raw('DATE(created_at)'), [$dateFrom, $dateTo])
            ->where('status', '!=', 'cancelled')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total_amount) as revenue')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return view('admin.reports.index', compact('summary', 'byModule', 'daily', 'dateFrom', 'dateTo'));
    }

    public function vendorReport(Request $request)
    {
        $dateFrom = $request->from ?? now()->startOfMonth()->toDateString();
        $dateTo   = $request->to   ?? now()->toDateString();

        $vendors = Vendor::withCount(['orders' => fn($q) =>
                $q->whereBetween(DB::raw('DATE(orders.created_at)'), [$dateFrom, $dateTo])
            ])
            ->withSum(['orders as orders_revenue' => fn($q) =>
                $q->whereBetween(DB::raw('DATE(orders.created_at)'), [$dateFrom, $dateTo])
                  ->where('status', '!=', 'cancelled')
            ], 'total_amount')
            ->orderByDesc('orders_count')
            ->paginate(20);

        return view('admin.reports.index', array_merge(
            compact('vendors', 'dateFrom', 'dateTo'),
            ['summary' => null, 'byModule' => collect(), 'daily' => collect()]
        ));
    }

    public function commissionReport(Request $request)
    {
        $dateFrom = $request->from ?? now()->startOfMonth()->toDateString();
        $dateTo   = $request->to   ?? now()->toDateString();

        $summary = Order::whereBetween(DB::raw('DATE(created_at)'), [$dateFrom, $dateTo])
            ->where('status', 'delivered')
            ->selectRaw('SUM(commission) as total_commission, COUNT(*) as total_orders, SUM(total_amount) as total_revenue')
            ->first();

        $byModule = Order::whereBetween(DB::raw('DATE(created_at)'), [$dateFrom, $dateTo])
            ->where('status', 'delivered')
            ->selectRaw('module_slug, SUM(commission) as commission, COUNT(*) as orders, SUM(total_amount) as revenue')
            ->groupBy('module_slug')
            ->get();

        return view('admin.reports.index', array_merge(
            compact('summary', 'byModule', 'dateFrom', 'dateTo'),
            ['daily' => collect(), 'vendors' => null]
        ));
    }

    public function exportSalesCsv(Request $request)
    {
        $dateFrom = $request->from ?? now()->startOfMonth()->toDateString();
        $dateTo   = $request->to   ?? now()->toDateString();

        $orders = Order::with(['vendor', 'user'])
            ->whereBetween(DB::raw('DATE(created_at)'), [$dateFrom, $dateTo])
            ->where('status', '!=', 'cancelled')
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sales-report.csv"',
        ];

        $callback = function () use ($orders) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Order #', 'Module', 'Vendor', 'Customer', 'Subtotal', 'Delivery', 'Discount', 'Total', 'Commission', 'Status', 'Date']);
            foreach ($orders as $order) {
                fputcsv($file, [
                    $order->order_number,
                    $order->module_slug,
                    $order->vendor?->name ?? 'N/A',
                    $order->user?->name   ?? 'N/A',
                    $order->subtotal,
                    $order->delivery_fee,
                    $order->discount,
                    $order->total_amount,
                    $order->commission,
                    $order->status,
                    $order->created_at->toDateTimeString(),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
