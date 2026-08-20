<?php

namespace App\Console\Commands\EWholesale;

use App\Models\EWholesale\{EWSupplier, EWOrder, EWDispute, EWInquiry};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ewholesale:supplier-stats — nightly computation of supplier scorecards.
 *
 * Updates per supplier:
 *  - response_rate          (% of inquiries replied within 48h)
 *  - response_time_avg      (median hours to first reply, last 90 days)
 *  - on_time_delivery_rate  (% orders delivered by expected_at, last 90 days)
 *  - dispute_rate           (disputed / total completed, last 90 days)
 *  - cancellation_rate      (cancelled / all orders, last 90 days)
 *  - rating                 (avg from ewholesale_reviews)
 *  - total_orders           (all-time non-cancelled)
 */
class ComputeSupplierStats extends Command
{
    protected $signature   = 'ewholesale:supplier-stats';
    protected $description = 'Nightly supplier scorecard computation';

    public function handle(): int
    {
        $window = now()->subDays(90);
        $updated = 0;

        EWSupplier::chunk(100, function ($suppliers) use ($window, &$updated) {
            foreach ($suppliers as $supplier) {
                $this->updateSupplier($supplier, $window);
                $updated++;
            }
        });

        $this->info("Updated {$updated} supplier scorecards.");
        return self::SUCCESS;
    }

    private function updateSupplier(EWSupplier $supplier, \Carbon\Carbon $window): void
    {
        $sid = $supplier->id;

        // ── response_rate & response_time_avg ─────────────────────────────
        // Using inquiry first-reply data from ewholesale_inquiry_replies (or quotes as proxy)
        // If inquiry replies aren't tracked separately, we use inquiry→quote timing
        $inquiries = DB::table('ewholesale_inquiries as i')
            ->where('i.supplier_id', $sid)
            ->where('i.created_at', '>=', $window)
            ->leftJoin(
                DB::raw('(SELECT MIN(created_at) as first_reply, inquiry_id
                          FROM ewholesale_quotes
                          GROUP BY inquiry_id) as q'),
                'q.inquiry_id', '=', 'i.id'
            )
            ->selectRaw('COUNT(*) as total,
                SUM(CASE WHEN q.first_reply IS NOT NULL
                         AND TIMESTAMPDIFF(HOUR, i.created_at, q.first_reply) <= 48
                         THEN 1 ELSE 0 END) as replied_in_48h,
                AVG(CASE WHEN q.first_reply IS NOT NULL
                         THEN TIMESTAMPDIFF(HOUR, i.created_at, q.first_reply)
                         ELSE NULL END) as avg_reply_hours')
            ->first();

        $responseRate    = $inquiries->total > 0
            ? round(($inquiries->replied_in_48h / $inquiries->total) * 100, 1)
            : $supplier->response_rate ?? 0;

        $responseTimeAvg = $inquiries->avg_reply_hours
            ? (int) round($inquiries->avg_reply_hours * 60) // minutes
            : ($supplier->response_time_avg ?? 0);

        // ── on-time delivery rate ─────────────────────────────────────────
        $deliveredOrders = EWOrder::where('supplier_id', $sid)
            ->where('status', 'delivered')
            ->where('updated_at', '>=', $window)
            ->whereNotNull('expected_at')
            ->selectRaw('COUNT(*) as total,
                SUM(CASE WHEN DATE(updated_at) <= expected_at THEN 1 ELSE 0 END) as on_time')
            ->first();

        $onTimeRate = ($deliveredOrders->total ?? 0) > 0
            ? round(($deliveredOrders->on_time / $deliveredOrders->total) * 100, 1)
            : 100.0;

        // ── dispute rate ──────────────────────────────────────────────────
        $completedCount = EWOrder::where('supplier_id', $sid)
            ->where('status', 'completed')
            ->where('created_at', '>=', $window)
            ->count();

        $disputeCount = EWDispute::whereHas('order', fn($q) => $q->where('supplier_id', $sid))
            ->where('created_at', '>=', $window)
            ->count();

        $disputeRate = $completedCount > 0
            ? round(($disputeCount / $completedCount) * 100, 1)
            : 0.0;

        // ── cancellation rate ─────────────────────────────────────────────
        $totalOrders90 = EWOrder::where('supplier_id', $sid)
            ->where('created_at', '>=', $window)
            ->count();

        $cancelledCount = EWOrder::where('supplier_id', $sid)
            ->where('status', 'cancelled')
            ->where('created_at', '>=', $window)
            ->count();

        $cancellationRate = $totalOrders90 > 0
            ? round(($cancelledCount / $totalOrders90) * 100, 1)
            : 0.0;

        // ── avg rating ────────────────────────────────────────────────────
        $rating = DB::table('ewholesale_reviews')
            ->where('supplier_id', $sid)
            ->avg('rating') ?? $supplier->rating ?? 0;

        // ── total orders (all-time non-cancelled) ─────────────────────────
        $totalOrders = EWOrder::where('supplier_id', $sid)
            ->where('status', '!=', 'cancelled')
            ->count();

        $supplier->update([
            'response_rate'        => $responseRate,
            'response_time_avg'    => $responseTimeAvg,
            'on_time_delivery_rate'=> $onTimeRate,
            'dispute_rate'         => $disputeRate,
            'cancellation_rate'    => $cancellationRate,
            'rating'               => round((float) $rating, 2),
            'total_orders'         => $totalOrders,
        ]);
    }
}
