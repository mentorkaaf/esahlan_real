<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\Commission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReportController extends Controller
{
    public function salesReport(Request $request)
    {
        $dateFrom = $request->from ?? now()->startOfMonth()->toDateString();
        $dateTo   = $request->to   ?? now()->toDateString();

        $base = Order::whereBetween(DB::raw('DATE(orders.created_at)'), [$dateFrom, $dateTo]);

        // ── Summary ────────────────────────────────────────────────────
        $summary = (clone $base)
            ->selectRaw('
                COUNT(*) as total_orders,
                COUNT(CASE WHEN status="cancelled" THEN 1 END) as cancelled_orders,
                SUM(CASE WHEN status!="cancelled" THEN total_amount  ELSE 0 END) as total_revenue,
                SUM(CASE WHEN status!="cancelled" THEN subtotal      ELSE 0 END) as total_subtotal,
                SUM(CASE WHEN status!="cancelled" THEN delivery_fee  ELSE 0 END) as total_delivery,
                SUM(CASE WHEN status!="cancelled" THEN COALESCE(bonus_amount,0) ELSE 0 END) as total_bonus,
                SUM(CASE WHEN status!="cancelled" THEN COALESCE(discount_amount,0)+COALESCE(coupon_discount,0) ELSE 0 END) as total_discounts,
                SUM(CASE WHEN status!="cancelled" THEN COALESCE(commission,0) ELSE 0 END) as total_commission,
                AVG(CASE WHEN status!="cancelled" THEN total_amount END) as avg_order_value
            ')
            ->first();

        // ── By Module ─────────────────────────────────────────────────
        $byModule = (clone $base)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('
                module_slug,
                COUNT(*) as orders,
                SUM(total_amount)   as revenue,
                SUM(subtotal)       as subtotal,
                SUM(delivery_fee)   as delivery_fees,
                SUM(COALESCE(bonus_amount,0)) as bonus,
                SUM(COALESCE(discount_amount,0)+COALESCE(coupon_discount,0)) as discounts,
                SUM(COALESCE(commission,0)) as commission
            ')
            ->groupBy('module_slug')
            ->orderByDesc('revenue')
            ->get();

        // ── Daily trend ───────────────────────────────────────────────
        $daily = (clone $base)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('DATE(orders.created_at) as date, COUNT(*) as orders, SUM(total_amount) as revenue, SUM(delivery_fee) as delivery_fees')
            ->groupBy(DB::raw('DATE(orders.created_at)'))
            ->orderBy('date')
            ->get();

        // ── Top Vendors ───────────────────────────────────────────────
        $topVendors = (clone $base)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('vendor_id')
            ->join('vendors', 'orders.vendor_id', '=', 'vendors.id')
            ->selectRaw('vendors.name as vendor_name, orders.module_slug, COUNT(*) as orders, SUM(orders.total_amount) as revenue, SUM(COALESCE(orders.commission,0)) as commission')
            ->groupBy('orders.vendor_id', 'vendors.name', 'orders.module_slug')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        return view('admin.reports.index', compact('summary','byModule','daily','topVendors','dateFrom','dateTo'));
    }

    public function vendorReport(Request $request)
    {
        $dateFrom = $request->from ?? now()->startOfMonth()->toDateString();
        $dateTo   = $request->to   ?? now()->toDateString();

        $vendors = Vendor::withCount(['orders as orders_count' => fn($q) =>
                $q->whereBetween(DB::raw('DATE(orders.created_at)'), [$dateFrom, $dateTo])
            ])
            ->withSum(['orders as orders_revenue' => fn($q) =>
                $q->whereBetween(DB::raw('DATE(orders.created_at)'), [$dateFrom, $dateTo])
                  ->where('status', '!=', 'cancelled')
            ], 'total_amount')
            ->withSum(['orders as orders_commission' => fn($q) =>
                $q->whereBetween(DB::raw('DATE(orders.created_at)'), [$dateFrom, $dateTo])
                  ->where('status', '!=', 'cancelled')
            ], 'commission')
            ->orderByDesc('orders_revenue')
            ->paginate(20);

        return view('admin.reports.index', array_merge(
            compact('vendors','dateFrom','dateTo'),
            ['summary' => null, 'byModule' => collect(), 'daily' => collect(), 'topVendors' => collect()]
        ));
    }

    public function commissionReport(Request $request)
    {
        $dateFrom = $request->from ?? now()->startOfMonth()->toDateString();
        $dateTo   = $request->to   ?? now()->toDateString();

        $summary = Order::whereBetween(DB::raw('DATE(orders.created_at)'), [$dateFrom, $dateTo])
            ->where('status', 'delivered')
            ->selectRaw('SUM(commission) as total_commission, COUNT(*) as total_orders, SUM(total_amount) as total_revenue')
            ->first();

        $byModule = Order::whereBetween(DB::raw('DATE(orders.created_at)'), [$dateFrom, $dateTo])
            ->where('status', 'delivered')
            ->selectRaw('module_slug, SUM(commission) as commission, COUNT(*) as orders, SUM(total_amount) as revenue')
            ->groupBy('module_slug')
            ->get();

        return view('admin.reports.index', array_merge(
            compact('summary','byModule','dateFrom','dateTo'),
            ['daily' => collect(), 'vendors' => null, 'topVendors' => collect()]
        ));
    }

    public function exportSalesCsv(Request $request)
    {
        $dateFrom = $request->from ?? now()->startOfMonth()->toDateString();
        $dateTo   = $request->to   ?? now()->toDateString();

        $orders = Order::with(['vendor','user'])
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$dateFrom, $dateTo])
            ->where('status', '!=', 'cancelled')
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="esahlan-sales-' . $dateFrom . '-to-' . $dateTo . '.csv"',
        ];

        $callback = function () use ($orders) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Order #','Module','Vendor','Customer','Subtotal','Delivery Fee','Bonus','Discount','Total','Commission','Status','Date']);
            foreach ($orders as $o) {
                fputcsv($file, [
                    $o->order_number,
                    $o->module_slug,
                    $o->vendor?->name ?? 'N/A',
                    $o->user?->name   ?? 'N/A',
                    $o->subtotal,
                    $o->delivery_fee,
                    $o->bonus_amount ?? 0,
                    ($o->discount_amount ?? 0) + ($o->coupon_discount ?? 0),
                    $o->total_amount,
                    $o->commission,
                    $o->status,
                    $o->created_at->toDateTimeString(),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
