<?php

namespace App\Http\Controllers\Api\EWholesale;

use App\Http\Controllers\Controller;
use App\Models\EWholesale\{EWBuyer, EWRfq, EWRfqQuote};
use App\Events\EWholesale\{EWBuyerEvent, EWSupplierEvent};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EWRfqApiController extends Controller
{
    // ── POST /api/v1/ewholesale/rfqs ─────────────────────────────────────
    public function store(Request $r)
    {
        $r->validate([
            'title'        => 'required|string|max:200',
            'category_id'  => 'nullable|exists:ewholesale_categories,id',
            'description'  => 'required|string|max:2000',
            'qty'          => 'required|numeric|min:1',
            'unit'         => 'required|string|max:30',
            'target_price' => 'nullable|numeric|min:0.01',
            'needed_by'    => 'nullable|date|after:today',
            'attachments'  => 'nullable|array',
            'attachments.*'=> 'file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $buyer  = $this->buyer($r);
        $expiry = now()->addDays((int) \App\Models\EWholesale\EWSetting::get('rfq_expiry_days', 14));

        $paths = [];
        foreach ($r->file('attachments', []) as $file) {
            $paths[] = $file->store('ewholesale/rfq', 'public');
        }

        $rfq = EWRfq::create([
            'buyer_id'    => $buyer->id,
            'title'       => $r->title,
            'category_id' => $r->category_id,
            'description' => $r->description,
            'qty'         => $r->qty,
            'unit'        => $r->unit,
            'target_price'=> $r->target_price,
            'needed_by'   => $r->needed_by,
            'attachments' => $paths ?: null,
            'status'      => 'open',
            'expires_at'  => $expiry,
        ]);

        // Notify active verified suppliers in same category
        $categoryId = $r->category_id;
        \App\Models\EWholesale\EWSupplier::active()->verified()
            ->whereHas('products', fn($q) => $q->where('category_id', $categoryId)->where('status','active'))
            ->pluck('id')
            ->unique()
            ->each(fn($sid) => \App\Jobs\EWholesale\NotifySupplierJob::dispatch(
                $sid, 'new_rfq', ['title' => $rfq->title, 'rfq_id' => $rfq->id]
            ));

        return response()->json([
            'message' => 'RFQ published. Suppliers will respond shortly.',
            'data'    => ['rfq_id' => $rfq->id, 'expires_at' => $expiry->toIso8601String()],
        ], 201);
    }

    // ── GET /api/v1/ewholesale/rfqs/mine ─────────────────────────────────
    public function mine(Request $r)
    {
        $buyer = $this->buyer($r);
        $rfqs  = EWRfq::with(['category:id,name','quotes.supplier:id,display_name'])
            ->where('buyer_id', $buyer->id)
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => $rfqs->getCollection()->map(fn($rfq) => [
                'id'          => $rfq->id,
                'title'       => $rfq->title,
                'status'      => $rfq->status,
                'qty'         => $rfq->qty,
                'unit'        => $rfq->unit,
                'target_price'=> $rfq->target_price,
                'quotes_count'=> $rfq->quotes->count(),
                'quotes'      => $rfq->quotes->map(fn($q) => [
                    'id'            => $q->id,
                    'supplier_name' => $q->supplier?->display_name,
                    'unit_price'    => $q->unit_price,
                    'qty_offered'   => $q->qty_offered,
                    'lead_time_days'=> $q->lead_time_days,
                    'status'        => $q->status,
                    'valid_until'   => $q->valid_until?->toDateString(),
                ]),
                'expires_at'  => $rfq->expires_at?->toIso8601String(),
                'created_at'  => $rfq->created_at->toIso8601String(),
            ]),
            'meta' => ['current_page'=>$rfqs->currentPage(),'last_page'=>$rfqs->lastPage(),'total'=>$rfqs->total()],
        ]);
    }

    // ── POST /api/v1/ewholesale/rfq-quotes/{id}/accept ───────────────────
    public function acceptQuote(Request $r, int $quoteId)
    {
        $buyer     = $this->buyer($r);
        $rfqQuote  = EWRfqQuote::with('rfq','supplier')->findOrFail($quoteId);

        if ($rfqQuote->rfq->buyer_id !== $buyer->id) abort(403);
        if ($rfqQuote->status !== 'sent' && $rfqQuote->status !== 'shortlisted') {
            return response()->json(['message' => 'Quote not available to accept.'], 422);
        }

        $rfqQuote->update(['status' => 'accepted']);
        $rfqQuote->rfq->update(['status' => 'awarded']);

        // Return order draft payload
        return response()->json([
            'message' => 'Quote accepted.',
            'data'    => [
                'order_draft' => [
                    'supplier_id' => $rfqQuote->supplier_id,
                    'lines'       => [[
                        'rfq_quote_id' => $rfqQuote->id,
                        'qty'          => $rfqQuote->qty_offered,
                        'unit_price'   => $rfqQuote->unit_price,
                    ]],
                    'total'      => $rfqQuote->qty_offered * $rfqQuote->unit_price,
                    'rfq_id'     => $rfqQuote->rfq_id,
                ],
            ],
        ]);
    }

    public function shortlistQuote(Request $r, int $quoteId)
    {
        $buyer    = $this->buyer($r);
        $rfqQuote = EWRfqQuote::with('rfq')->findOrFail($quoteId);
        if ($rfqQuote->rfq->buyer_id !== $buyer->id) abort(403);
        $rfqQuote->update(['status' => 'shortlisted']);
        return response()->json(['message' => 'Quote shortlisted.']);
    }

    public function rejectQuote(Request $r, int $quoteId)
    {
        $buyer    = $this->buyer($r);
        $rfqQuote = EWRfqQuote::with('rfq')->findOrFail($quoteId);
        if ($rfqQuote->rfq->buyer_id !== $buyer->id) abort(403);
        $rfqQuote->update(['status' => 'rejected']);
        return response()->json(['message' => 'Quote rejected.']);
    }

    private function buyer(Request $r): EWBuyer
    {
        $b = EWBuyer::where('user_id', $r->user()->id)->first();
        abort_unless($b, 404, 'Buyer profile not found.');
        return $b;
    }
}
