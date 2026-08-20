<?php

namespace App\Http\Controllers\Api\EWholesale;

use App\Http\Controllers\Controller;
use App\Models\EWholesale\{
    EWBuyer, EWProduct, EWProductVariant, EWInquiry, EWQuote, EWSupplier
};
use App\Events\EWholesale\{EWBuyerEvent, EWSupplierEvent};
use App\Services\EWholesale\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EWQuoteController extends Controller
{
    public function __construct(private PricingService $pricing) {}

    // ── POST /api/v1/ewholesale/products/{id}/inquiry ─────────────────────
    public function inquiry(Request $r, int $productId)
    {
        $r->validate([
            'qty'     => 'required|numeric|min:1',
            'message' => 'required|string|max:500',
        ]);

        $buyer   = $this->buyer($r);
        $product = EWProduct::findOrFail($productId);
        $supplier= $product->supplier;

        if (!$supplier) {
            return response()->json(['message' => 'Supplier not found.'], 404);
        }

        $inquiry = EWInquiry::create([
            'buyer_id'    => $buyer->id,
            'supplier_id' => $supplier->id,
            'product_id'  => $product->id,
            'qty'         => $r->qty,
            'message'     => $r->message,
        ]);

        // Notify supplier via broadcast + FCM
        if ($supplier->vendor_id) {
            broadcast(new EWSupplierEvent(
                $supplier->vendor_id,
                'inquiry_received',
                [
                    'inquiry_id'   => $inquiry->id,
                    'product_name' => $product->name,
                    'qty'          => $r->qty,
                    'buyer_name'   => $r->user()->name,
                ]
            ))->toOthers();
        }
        \App\Jobs\EWholesale\NotifySupplierJob::dispatch(
            $supplier->id, 'new_inquiry',
            ['product_name' => $product->name, 'buyer_name' => $r->user()->name]
        );

        return response()->json([
            'message' => 'Inquiry sent to supplier.',
            'data'    => ['inquiry_id' => $inquiry->id],
        ], 201);
    }

    // ── GET /api/v1/ewholesale/quotes ─────────────────────────────────────
    public function index(Request $r)
    {
        $buyer = $this->buyer($r);
        $quotes = EWQuote::with('supplier:id,display_name,logo,verification')
            ->where('buyer_id', $buyer->id)
            ->whereNull('counter_of_id') // top-level quotes only
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => $quotes->getCollection()->map(fn($q) => $this->quotePayload($q)),
            'meta' => ['current_page'=>$quotes->currentPage(),'last_page'=>$quotes->lastPage(),'total'=>$quotes->total()],
        ]);
    }

    // ── GET /api/v1/ewholesale/quotes/{id} ───────────────────────────────
    public function show(Request $r, int $id)
    {
        $buyer = $this->buyer($r);
        $quote = EWQuote::with(['supplier:id,display_name,logo','counters'])->findOrFail($id);

        if ($quote->buyer_id !== $buyer->id) abort(403);

        return response()->json(['data' => $this->quotePayload($quote, detailed: true)]);
    }

    // ── POST /api/v1/ewholesale/quotes/{id}/counter ───────────────────────
    public function counter(Request $r, int $id)
    {
        $r->validate([
            'lines'       => 'required|array|min:1',
            'lines.*.product_id'   => 'required|integer',
            'lines.*.variant_id'   => 'nullable|integer',
            'lines.*.qty'          => 'required|numeric|min:1',
            'lines.*.unit_price'   => 'required|numeric|min:0.01',
            'note'        => 'nullable|string|max:300',
        ]);

        $buyer = $this->buyer($r);
        $orig  = EWQuote::findOrFail($id);
        if ($orig->buyer_id !== $buyer->id) abort(403);
        if (!in_array($orig->status, ['sent','countered'])) {
            return response()->json(['message' => 'Cannot counter a '.$orig->status.' quote.'], 422);
        }

        $lines    = collect($r->lines);
        $subtotal = $lines->sum(fn($l) => $l['qty'] * $l['unit_price']);
        $total    = round($subtotal + $orig->delivery_fee, 2);

        $counter = EWQuote::create([
            'inquiry_id'   => $orig->inquiry_id,
            'buyer_id'     => $buyer->id,
            'supplier_id'  => $orig->supplier_id,
            'lines'        => $lines->toArray(),
            'subtotal'     => $subtotal,
            'delivery_fee' => $orig->delivery_fee,
            'total'        => $total,
            'payment_term' => $orig->payment_term,
            'valid_until'  => now()->addDays(3)->toDateString(),
            'status'       => 'countered',
            'counter_of_id'=> $orig->id,
        ]);

        $orig->update(['status' => 'countered']);

        // Notify supplier
        if ($orig->supplier?->vendor_id) {
            broadcast(new EWSupplierEvent(
                $orig->supplier->vendor_id,
                'quote_countered',
                ['quote_id' => $counter->id, 'buyer_name' => $r->user()->name]
            ))->toOthers();
        }

        return response()->json(['message' => 'Counter-offer sent.', 'data' => ['quote_id' => $counter->id]], 201);
    }

    // ── POST /api/v1/ewholesale/quotes/{id}/accept ────────────────────────
    public function accept(Request $r, int $id)
    {
        $buyer = $this->buyer($r);
        $quote = EWQuote::findOrFail($id);
        if ($quote->buyer_id !== $buyer->id) abort(403);
        if (!in_array($quote->status, ['sent','countered'])) {
            return response()->json(['message' => 'Quote is not in an acceptable state.'], 422);
        }
        if ($quote->isExpired()) {
            return response()->json(['message' => 'Quote has expired.'], 422);
        }

        $quote->update(['status' => 'accepted', 'accepted_at' => now()]);

        // Return an order draft payload the Flutter app will pass to POST /orders
        return response()->json([
            'message' => 'Quote accepted. Proceed to checkout.',
            'data'    => [
                'order_draft' => [
                    'supplier_id' => $quote->supplier_id,
                    'lines'       => $quote->lines,
                    'total'       => $quote->total,
                    'payment_term'=> $quote->payment_term,
                    'quote_id'    => $quote->id,
                ],
            ],
        ]);
    }

    // ── POST /api/v1/ewholesale/quotes/{id}/decline ───────────────────────
    public function decline(Request $r, int $id)
    {
        $buyer = $this->buyer($r);
        $quote = EWQuote::findOrFail($id);
        if ($quote->buyer_id !== $buyer->id) abort(403);
        $quote->update(['status' => 'declined']);
        return response()->json(['message' => 'Quote declined.']);
    }

    // ─────────────────────────────────────────────────────────────────────
    private function buyer(Request $r): EWBuyer
    {
        $buyer = EWBuyer::where('user_id', $r->user()->id)->first();
        abort_unless($buyer, 404, 'Buyer profile not found. Please register first.');
        return $buyer;
    }

    private function quotePayload(EWQuote $q, bool $detailed = false): array
    {
        $secondsLeft = $q->valid_until ? max(0, now()->diffInSeconds($q->valid_until->endOfDay(), false)) : null;
        $base = [
            'id'             => $q->id,
            'supplier_name'  => $q->supplier?->display_name,
            'status'         => $q->status,
            'subtotal'       => $q->subtotal,
            'delivery_fee'   => $q->delivery_fee,
            'total'          => $q->total,
            'payment_term'   => $q->payment_term,
            'valid_until'    => $q->valid_until?->toDateString(),
            'seconds_left'   => $secondsLeft,
            'created_at'     => $q->created_at->toIso8601String(),
        ];

        if ($detailed) {
            $base['lines']    = $q->lines;
            $base['counters'] = $q->counters->map(fn($c) => ['id'=>$c->id,'status'=>$c->status,'total'=>$c->total,'created_at'=>$c->created_at->toIso8601String()]);
            $base['counter_of_id'] = $q->counter_of_id;
        }

        return $base;
    }
}
