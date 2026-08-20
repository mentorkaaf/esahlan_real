<?php

namespace App\Http\Controllers\Api\EWholesale;

use App\Http\Controllers\Controller;
use App\Models\EWholesale\{
    EWSupplier, EWProduct, EWProductVariant, EWPriceTier,
    EWOrder, EWInquiry, EWQuote, EWRfq, EWRfqQuote, EWActivityLog
};
use App\Models\Vendor;
use App\Services\EWholesale\CatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Cache};

/**
 * Supplier self-service portal.
 *
 * Auth: Sanctum token of a user who has a linked Vendor + EWSupplier.
 * All routes prefixed /api/v1/ewholesale/supplier/*
 */
class EWSupplierPortalController extends Controller
{
    // ── Helper: resolve supplier from authed user ─────────────────────────────

    private function resolveSupplier(): ?EWSupplier
    {
        $user = auth()->user();

        // User may be attached to a Vendor directly (vendor_id on user)
        $vendorId = $user->vendor_id
            ?? Vendor::where('user_id', $user->id)->value('id');

        if (!$vendorId) return null;

        return EWSupplier::where('vendor_id', $vendorId)->where('is_active', true)->first();
    }

    private function supplierOrFail(): EWSupplier
    {
        $supplier = $this->resolveSupplier();
        abort_unless($supplier, 403, 'No active wholesale supplier profile found for your account.');
        return $supplier;
    }

    // ── GET /supplier/me ──────────────────────────────────────────────────────

    public function me()
    {
        $supplier = $this->resolveSupplier();

        if (!$supplier) {
            return response()->json(['registered' => false, 'message' => 'Not registered as a wholesale supplier.'], 404);
        }

        return response()->json(['data' => $this->supplierResource($supplier)]);
    }

    // ── POST /supplier/register ───────────────────────────────────────────────

    public function register(Request $r)
    {
        $user = auth()->user();
        $vendorId = $user->vendor_id
            ?? Vendor::where('user_id', $user->id)->value('id');

        if (!$vendorId) {
            return response()->json(['error' => 'You must have a vendor account to register as a wholesale supplier. Contact admin.'], 403);
        }

        if (EWSupplier::where('vendor_id', $vendorId)->exists()) {
            return response()->json(['error' => 'Already registered as a wholesale supplier.'], 409);
        }

        $r->validate([
            'display_name'      => 'required|string|max:120',
            'about'             => 'nullable|string|max:1000',
            'warehouse_address' => 'nullable|string|max:255',
        ]);

        $supplier = EWSupplier::create([
            'vendor_id'         => $vendorId,
            'display_name'      => $r->display_name,
            'about'             => $r->about,
            'warehouse_address' => $r->warehouse_address,
            'verification'      => 'pending',
            'is_active'         => true,
        ]);

        EWActivityLog::record('supplier.self_registered', $supplier, [], $supplier->toArray(), 'supplier', $user->id);

        return response()->json(['message' => 'Registration submitted. Admin will review and verify your profile.', 'data' => $this->supplierResource($supplier)], 201);
    }

    // ── PUT /supplier/me ──────────────────────────────────────────────────────

    public function updateProfile(Request $r)
    {
        $supplier = $this->supplierOrFail();

        $r->validate([
            'display_name'      => 'sometimes|string|max:120',
            'about'             => 'nullable|string|max:1000',
            'warehouse_address' => 'nullable|string|max:255',
        ]);

        $supplier->update($r->only(['display_name','about','warehouse_address']));

        return response()->json(['data' => $this->supplierResource($supplier)]);
    }

    // ── GET /supplier/dashboard ───────────────────────────────────────────────

    public function dashboard()
    {
        $supplier = $this->supplierOrFail();

        $pendingOrders  = EWOrder::where('supplier_id', $supplier->id)->where('status', 'pending_confirmation')->count();
        $activeOrders   = EWOrder::where('supplier_id', $supplier->id)->whereNotIn('status', ['cancelled','completed','delivered'])->count();
        $gmv30          = EWOrder::where('supplier_id', $supplier->id)->whereNotIn('status',['cancelled'])->where('created_at','>=',now()->subDays(30))->sum('total');
        $gmvTotal       = EWOrder::where('supplier_id', $supplier->id)->whereNotIn('status',['cancelled'])->sum('total');
        $openInquiries  = EWInquiry::where('supplier_id', $supplier->id)->where('status','pending')->count();
        $productsCount  = EWProduct::where('supplier_id', $supplier->id)->where('status','active')->count();

        // Recent orders
        $recentOrders = EWOrder::where('supplier_id', $supplier->id)
            ->with('buyer:id,business_name','items')
            ->latest()->limit(5)->get()
            ->map(fn($o) => [
                'id'         => $o->id,
                'order_no'   => $o->order_no,
                'status'     => $o->status,
                'total'      => (float)$o->total,
                'buyer_name' => $o->buyer?->business_name ?? '—',
                'created_at' => $o->created_at->toDateString(),
            ]);

        return response()->json(['data' => compact(
            'pendingOrders','activeOrders','gmv30','gmvTotal',
            'openInquiries','productsCount','recentOrders'
        )]);
    }

    // ── PRODUCTS ──────────────────────────────────────────────────────────────

    public function products(Request $r)
    {
        $supplier = $this->supplierOrFail();

        $products = EWProduct::where('supplier_id', $supplier->id)
            ->with(['category:id,name','variants'])
            ->when($r->status, fn($q) => $q->where('status', $r->status))
            ->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return response()->json(['data' => $products->through(fn($p) => [
            'id'          => $p->id,
            'name'        => $p->name,
            'slug'        => $p->slug,
            'category'    => $p->category?->name,
            'status'      => $p->status,
            'moq'         => $p->moq,
            'unit'        => $p->unit,
            'min_price'   => $p->min_price,
            'max_price'   => $p->max_price,
            'orders_count'=> $p->orders_count,
            'variants_count' => $p->variants->count(),
            'created_at'  => $p->created_at->toDateString(),
        ])]);
    }

    public function productShow(int $id)
    {
        $supplier = $this->supplierOrFail();
        $product  = EWProduct::where('supplier_id', $supplier->id)->with(['category','variants','priceTiers'])->findOrFail($id);

        return response()->json(['data' => $product]);
    }

    public function productStore(Request $r)
    {
        $supplier = $this->supplierOrFail();

        if (!$supplier->isVerified()) {
            return response()->json(['error' => 'Your supplier profile must be verified before adding products. Contact admin.'], 403);
        }

        $r->validate([
            'name'        => 'required|string|max:200',
            'category_id' => 'required|exists:ewholesale_categories,id',
            'description' => 'nullable|string',
            'moq'         => 'required|numeric|min:1',
            'unit'        => 'required|string|max:30',
            'lead_time_days' => 'nullable|integer|min:1',
        ]);

        $product = EWProduct::create([
            'supplier_id'    => $supplier->id,
            'name'           => $r->name,
            'slug'           => \Str::slug($r->name).'-'.uniqid(),
            'category_id'    => $r->category_id,
            'description'    => $r->description,
            'moq'            => $r->moq,
            'unit'           => $r->unit,
            'lead_time_days' => $r->lead_time_days,
            'status'         => 'pending_review', // requires admin approval
        ]);

        return response()->json(['message' => 'Product submitted for review.', 'data' => ['id' => $product->id]], 201);
    }

    public function productUpdate(Request $r, int $id)
    {
        $supplier = $this->supplierOrFail();
        $product  = EWProduct::where('supplier_id', $supplier->id)->findOrFail($id);

        $r->validate([
            'name'           => 'sometimes|string|max:200',
            'description'    => 'nullable|string',
            'moq'            => 'sometimes|numeric|min:1',
            'lead_time_days' => 'nullable|integer|min:1',
        ]);

        $product->update($r->only(['name','description','moq','lead_time_days']));

        // Re-submit for review if major fields changed
        if ($r->has('name') || $r->has('description')) {
            $product->update(['status' => 'pending_review']);
        }

        return response()->json(['data' => $product]);
    }

    public function productToggle(int $id)
    {
        $supplier = $this->supplierOrFail();
        $product  = EWProduct::where('supplier_id', $supplier->id)->findOrFail($id);

        $newStatus = $product->status === 'active' ? 'inactive' : 'active';
        $product->update(['status' => $newStatus]);

        return response()->json(['status' => $newStatus]);
    }

    // ── ORDERS ────────────────────────────────────────────────────────────────

    public function orders(Request $r)
    {
        $supplier = $this->supplierOrFail();

        $orders = EWOrder::where('supplier_id', $supplier->id)
            ->with(['buyer:id,business_name','items.product:id,name'])
            ->when($r->status, fn($q) => $q->where('status', $r->status))
            ->latest()->paginate(20)->withQueryString();

        return response()->json(['data' => $orders->through(fn($o) => [
            'id'           => $o->id,
            'order_no'     => $o->order_no,
            'status'       => $o->status,
            'total'        => (float)$o->total,
            'payment_plan' => $o->payment_plan,
            'buyer_name'   => $o->buyer?->business_name ?? '—',
            'items_count'  => $o->items->count(),
            'created_at'   => $o->created_at->toDateString(),
        ])]);
    }

    public function orderShow(int $id)
    {
        $supplier = $this->supplierOrFail();
        $order    = EWOrder::where('supplier_id', $supplier->id)
            ->with(['buyer.user:id,name,phone','items.product:id,name,unit','payments','shipments'])
            ->findOrFail($id);

        return response()->json(['data' => $order]);
    }

    public function orderConfirm(int $id)
    {
        $supplier = $this->supplierOrFail();
        $order    = EWOrder::where('supplier_id', $supplier->id)
            ->where('status', 'pending_confirmation')
            ->findOrFail($id);

        $order->update(['status' => 'confirmed', 'confirmed_at' => now()]);

        // Notify buyer
        \App\Jobs\EWholesale\NotifySupplierJob::dispatch($supplier->id, 'order_confirmed', [
            'order_id' => $order->id, 'order_no' => $order->order_no,
        ]);

        return response()->json(['message' => 'Order confirmed.', 'status' => 'confirmed']);
    }

    public function orderUpdateStatus(Request $r, int $id)
    {
        $supplier = $this->supplierOrFail();
        $order    = EWOrder::where('supplier_id', $supplier->id)->findOrFail($id);

        $allowed = match($order->status) {
            'confirmed'        => ['processing'],
            'awaiting_payment' => ['processing'],
            'processing'       => ['ready'],
            'ready'            => ['shipped','partially_shipped'],
            'partially_shipped'=> ['shipped'],
            'shipped'          => ['delivered'],
            default            => [],
        };

        $r->validate(['status' => 'required|in:'.implode(',', $allowed)]);

        $order->update(['status' => $r->status]);

        return response()->json(['message' => 'Order status updated.', 'status' => $r->status]);
    }

    // ── INQUIRIES (Buyer sent inquiry → Supplier responds with quote) ─────────

    public function inquiries(Request $r)
    {
        $supplier = $this->supplierOrFail();

        $inquiries = EWInquiry::where('supplier_id', $supplier->id)
            ->with(['buyer:id,business_name','product:id,name','quotes'])
            ->when($r->status, fn($q) => $q->where('status', $r->status))
            ->latest()->paginate(20)->withQueryString();

        return response()->json(['data' => $inquiries->through(fn($i) => [
            'id'          => $i->id,
            'status'      => $i->status,
            'product_name'=> $i->product?->name ?? '—',
            'buyer_name'  => $i->buyer?->business_name ?? '—',
            'qty'         => $i->qty,
            'unit'        => $i->unit,
            'message'     => $i->message,
            'quotes_count'=> $i->quotes->count(),
            'created_at'  => $i->created_at->toDateString(),
        ])]);
    }

    public function sendQuote(Request $r, int $inquiryId)
    {
        $supplier = $this->supplierOrFail();
        $inquiry  = EWInquiry::where('supplier_id', $supplier->id)->findOrFail($inquiryId);

        if (!in_array($inquiry->status, ['pending','countered'])) {
            return response()->json(['error' => 'Cannot quote on this inquiry (status: '.$inquiry->status.')'], 422);
        }

        $r->validate([
            'unit_price'  => 'required|numeric|min:0',
            'valid_days'  => 'integer|min:1|max:30',
            'note'        => 'nullable|string|max:500',
            'min_qty'     => 'nullable|numeric|min:1',
        ]);

        $quote = EWQuote::create([
            'inquiry_id'  => $inquiry->id,
            'supplier_id' => $supplier->id,
            'buyer_id'    => $inquiry->buyer_id,
            'product_id'  => $inquiry->product_id,
            'qty'         => $inquiry->qty,
            'unit_price'  => $r->unit_price,
            'total'       => round($r->unit_price * $inquiry->qty, 2),
            'valid_until' => now()->addDays($r->valid_days ?? 7),
            'note'        => $r->note,
            'status'      => 'sent',
        ]);

        $inquiry->update(['status' => 'quoted']);

        // Notify buyer via private-user channel
        \App\Jobs\EWholesale\NotifySupplierJob::dispatch($supplier->id, 'quote_sent', [
            'quote_id'   => $quote->id,
            'inquiry_id' => $inquiry->id,
        ]);

        return response()->json(['message' => 'Quote sent to buyer.', 'data' => $quote], 201);
    }

    // ── RFQs (Open RFQs supplier can respond to) ──────────────────────────────

    public function rfqs(Request $r)
    {
        $supplier = $this->supplierOrFail();

        // Show open RFQs in categories the supplier has products in
        $categoryIds = EWProduct::where('supplier_id', $supplier->id)
            ->where('status', 'active')
            ->pluck('category_id')
            ->unique();

        $rfqs = EWRfq::where('status', 'open')
            ->where('expires_at', '>', now())
            ->when($categoryIds->isNotEmpty(), fn($q) => $q->whereIn('category_id', $categoryIds))
            ->with(['buyer:id,business_name','category:id,name'])
            ->latest()->paginate(20)->withQueryString();

        return response()->json(['data' => $rfqs->through(fn($r) => [
            'id'           => $r->id,
            'title'        => $r->title,
            'category'     => $r->category?->name,
            'buyer_name'   => $r->buyer?->business_name ?? '—',
            'qty'          => $r->qty,
            'unit'         => $r->unit,
            'target_price' => $r->target_price,
            'needed_by'    => $r->needed_by?->toDateString(),
            'expires_at'   => $r->expires_at->toDateString(),
            'quotes_count' => EWRfqQuote::where('rfq_id', $r->id)->count(),
            'i_quoted'     => EWRfqQuote::where('rfq_id', $r->id)->where('supplier_id', $supplier->id)->exists(),
        ])]);
    }

    public function rfqSubmitQuote(Request $r, int $rfqId)
    {
        $supplier = $this->supplierOrFail();
        $rfq      = EWRfq::where('status','open')->where('expires_at','>',now())->findOrFail($rfqId);

        if (EWRfqQuote::where('rfq_id', $rfqId)->where('supplier_id', $supplier->id)->exists()) {
            return response()->json(['error' => 'You already submitted a quote for this RFQ.'], 409);
        }

        $r->validate([
            'unit_price'  => 'required|numeric|min:0',
            'lead_days'   => 'required|integer|min:1',
            'valid_days'  => 'integer|min:1|max:30',
            'note'        => 'nullable|string|max:500',
        ]);

        $quote = EWRfqQuote::create([
            'rfq_id'      => $rfq->id,
            'supplier_id' => $supplier->id,
            'unit_price'  => $r->unit_price,
            'total'       => round($r->unit_price * $rfq->qty, 2),
            'lead_days'   => $r->lead_days,
            'valid_until' => now()->addDays($r->valid_days ?? 7),
            'note'        => $r->note,
            'status'      => 'pending',
        ]);

        return response()->json(['message' => 'Quote submitted for RFQ.', 'data' => $quote], 201);
    }

    // ── Private helper ─────────────────────────────────────────────────────────

    private function supplierResource(EWSupplier $s): array
    {
        return [
            'id'               => $s->id,
            'display_name'     => $s->display_name,
            'logo_url'         => $s->logo ? url('storage/'.$s->logo) : null,
            'about'            => $s->about,
            'warehouse_address'=> $s->warehouse_address,
            'verification'     => $s->verification,
            'is_verified'      => $s->isVerified(),
            'rating'           => $s->rating,
            'response_rate'    => $s->response_rate,
            'total_orders'     => $s->total_orders,
            'is_active'        => $s->is_active,
        ];
    }
}
