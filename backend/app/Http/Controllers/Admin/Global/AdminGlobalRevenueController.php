<?php

namespace App\Http\Controllers\Admin\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalPayment;
use App\Models\Global\GlobalOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminGlobalRevenueController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', '30');
        $from   = $request->filled('from') ? $request->from : now()->subDays((int)$period)->startOfDay();
        $to     = $request->filled('to')   ? $request->to . ' 23:59:59' : now()->endOfDay();

        // Revenue by period
        $revenueByDay = GlobalPayment::where('status', 'paid')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->selectRaw('DATE(created_at) as date, SUM(amount) as revenue, COUNT(*) as transactions')
            ->orderBy('date')
            ->get();

        // Revenue by payment method
        $byMethod = GlobalPayment::where('status', 'paid')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('method')
            ->selectRaw('method, SUM(amount) as revenue, COUNT(*) as count')
            ->get();

        // Revenue by country
        $byCountry = GlobalOrder::whereHas('payment', fn($q) => $q->where('status','paid'))
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('shipping_country')
            ->selectRaw('shipping_country, SUM(total) as revenue, COUNT(*) as orders')
            ->orderByDesc('revenue')
            ->limit(15)
            ->get();

        // Totals
        $totals = [
            'gross'    => GlobalPayment::where('status','paid')->whereBetween('created_at',[$from,$to])->sum('amount'),
            'refunded' => GlobalPayment::where('status','refunded')->whereBetween('created_at',[$from,$to])->sum('amount'),
            'net'      => 0,
            'txns'     => GlobalPayment::where('status','paid')->whereBetween('created_at',[$from,$to])->count(),
            'orders'   => GlobalOrder::whereHas('payment',fn($q)=>$q->where('status','paid'))->whereBetween('created_at',[$from,$to])->count(),
        ];
        $totals['net'] = $totals['gross'] - $totals['refunded'];

        // Previous period for comparison
        $prevFrom  = (clone (is_string($from) ? \Carbon\Carbon::parse($from) : $from))->subDays($period);
        $prevTo    = (clone (is_string($from) ? \Carbon\Carbon::parse($from) : $from))->subDay();
        $prevGross = GlobalPayment::where('status','paid')->whereBetween('created_at',[$prevFrom,$prevTo])->sum('amount');
        $growth    = $prevGross > 0 ? round(($totals['gross'] - $prevGross) / $prevGross * 100, 1) : 0;

        return view('admin.global.revenue.index', compact(
            'revenueByDay','byMethod','byCountry','totals','growth','period','from','to'
        ));
    }

    public function export(Request $request)
    {
        $from = $request->get('from', now()->startOfMonth());
        $to   = $request->get('to', now()->endOfDay());

        $payments = GlobalPayment::with('order.user')
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->get();

        $csv  = "Date,Order#,Customer,Email,Method,Amount,Status\n";
        foreach ($payments as $p) {
            $csv .= implode(',', [
                $p->created_at->format('Y-m-d'),
                $p->order?->order_number ?? '',
                '"' . ($p->order?->user?->name ?? '') . '"',
                $p->order?->user?->email ?? '',
                $p->method,
                number_format($p->amount, 2),
                $p->status,
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="revenue-export-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }
}
