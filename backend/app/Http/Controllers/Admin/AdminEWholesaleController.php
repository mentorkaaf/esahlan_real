<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EWholesale\{
    EWSupplier, EWBuyer, EWCreditAccount, EWCreditLedger, EWProduct, EWProductVariant,
    EWCategory, EWPriceTier, EWPriceList, EWBuyerPriceList, EWDeal,
    EWOrder, EWOrderItem, EWOrderPayment, EWShipment, EWDispute,
    EWRfq, EWRfqQuote, EWShippingRule, EWSetting, EWActivityLog, EWBanner, EWReview
};
use App\Models\Vendor;
use App\Services\EWholesale\CreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Cache, Validator};
use Illuminate\Support\Str;

class AdminEWholesaleController extends Controller
{
    public function __construct(private CreditService $credit) {}

    // ────────────────────────────────────────────────────────────
    // DASHBOARD
    // ────────────────────────────────────────────────────────────

    public function dashboard()
    {
        // KPI cards
        $gmvTotal    = EWOrder::whereNotIn('status', ['cancelled'])->sum('total');
        $gmv30       = EWOrder::whereNotIn('status', ['cancelled'])
                               ->where('created_at', '>=', now()->subDays(30))->sum('total');
        $ordersCount = EWOrder::count();
        $awaitingConf= EWOrder::where('status', 'pending_confirmation')->count();
        $openRfqs    = EWRfq::where('status', 'open')->count();
        $pendingVerif= EWSupplier::where('verification', 'pending')->count();
        $pendingKyb  = EWBuyer::where('kyb_status', 'pending')->count();
        $openDisp    = EWDispute::where('status', 'open')->count();
        $creditExp   = EWCreditAccount::where('status', 'active')->sum('balance_used');

        // 12-week GMV chart
        $gmvChart = collect(range(11, 0))->map(function ($weeksAgo) {
            $start = now()->startOfWeek()->subWeeks($weeksAgo);
            $end   = (clone $start)->endOfWeek();
            return [
                'week'  => $start->format('M d'),
                'gmv'   => EWOrder::whereNotIn('status', ['cancelled'])
                                    ->whereBetween('created_at', [$start, $end])
                                    ->sum('total'),
            ];
        });

        // Top suppliers (last 30 days)
        $topSuppliers = EWOrder::selectRaw('supplier_id, COUNT(*) as cnt, SUM(total) as gmv')
            ->whereNotIn('status', ['cancelled'])
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('supplier_id')
            ->orderByDesc('gmv')
            ->limit(5)
            ->with('supplier:id,display_name,logo')
            ->get();

        // Top categories
        $topCategories = EWOrderItem::selectRaw('p.category_id, COUNT(*) as cnt, SUM(oi.line_total) as revenue')
            ->from('ewholesale_order_items as oi')
            ->join('ewholesale_products as p', 'p.id', '=', 'oi.product_id')
            ->join('ewholesale_orders as o', 'o.id', '=', 'oi.order_id')
            ->whereNotIn('o.status', ['cancelled'])
            ->groupBy('p.category_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn($r) => array_merge($r->toArray(), [
                'category' => EWCategory::find($r->category_id),
            ]));

        // Needs attention
        $attentionItems = collect();
        if ($awaitingConf)  $attentionItems->push(['type'=>'warning','msg'=>"{$awaitingConf} orders awaiting confirmation",'url'=>route('admin.module-data.wholesale.orders',['status'=>'pending_confirmation'])]);
        if ($openDisp)      $attentionItems->push(['type'=>'danger', 'msg'=>"{$openDisp} open disputes",'url'=>route('admin.module-data.wholesale.orders',['tab'=>'disputes'])]);
        if ($pendingVerif)  $attentionItems->push(['type'=>'info',   'msg'=>"{$pendingVerif} suppliers pending verification",'url'=>route('admin.module-data.wholesale.suppliers')]);
        if ($pendingKyb)    $attentionItems->push(['type'=>'info',   'msg'=>"{$pendingKyb} buyers pending KYB",'url'=>route('admin.module-data.wholesale.buyers')]);

        return view('admin.ewholesale.dashboard', compact(
            'gmvTotal','gmv30','ordersCount','awaitingConf','openRfqs',
            'pendingVerif','pendingKyb','openDisp','creditExp',
            'gmvChart','topSuppliers','topCategories','attentionItems'
        ));
    }

    // ────────────────────────────────────────────────────────────
    // SUPPLIERS
    // ────────────────────────────────────────────────────────────

    public function suppliers(Request $r)
    {
        $q = EWSupplier::with('vendor:id,name')->withCount('products');
        if ($r->search)       $q->where('display_name','like',"%{$r->search}%");
        if ($r->verification) $q->where('verification', $r->verification);
        $suppliers = $q->orderByDesc('id')->paginate(20)->withQueryString();
        return view('admin.ewholesale.suppliers.index', compact('suppliers'));
    }

    public function supplierShow(EWSupplier $supplier)
    {
        $supplier->load(['vendor','products.category','shippingRules']);
        $orders   = EWOrder::where('supplier_id',$supplier->id)->with('buyer.user:id,name')->latest()->limit(10)->get();
        $gmv      = EWOrder::where('supplier_id',$supplier->id)->whereNotIn('status',['cancelled'])->sum('total');
        $logs     = EWActivityLog::where('subject_type','supplier')->where('subject_id',$supplier->id)->latest()->limit(20)->get();
        return view('admin.ewholesale.suppliers.show', compact('supplier','orders','gmv','logs'));
    }

    public function supplierAction(Request $r, EWSupplier $supplier)
    {
        $action = $r->action;
        $before = $supplier->only(['verification','is_active']);

        match($action) {
            'verify'  => $supplier->update(['verification'=>'verified',  'verified_at'=>now(), 'is_active'=>true]),
            'gold'    => $supplier->update(['verification'=>'gold',      'verified_at'=>now(), 'is_active'=>true]),
            'suspend' => $supplier->update(['verification'=>'unverified','is_active'=>false]),
            'activate'=> $supplier->update(['is_active'=>true]),
            default   => abort(422, 'Unknown action'),
        };

        EWActivityLog::record('supplier.'.$action, $supplier, $before, $supplier->fresh()->only(['verification','is_active']), 'admin', auth()->id());
        return back()->with('success', "Supplier {$action}d.");
    }

    public function supplierShippingUpdate(Request $r, EWSupplier $supplier)
    {
        $r->validate([
            'rules'                => 'required|array',
            'rules.*.basis'        => 'required|in:per_carton,per_kg,per_cbm,flat_by_zone',
            'rules.*.rate'         => 'required|numeric|min:0',
            'rules.*.free_over'    => 'nullable|numeric|min:0',
            'rules.*.zone'         => 'nullable|string|max:100',
            'rules.*.is_active'    => 'boolean',
        ]);

        // Delete existing and re-insert
        EWShippingRule::where('supplier_id', $supplier->id)->delete();
        foreach ($r->rules as $rule) {
            EWShippingRule::create(array_merge($rule, ['supplier_id' => $supplier->id]));
        }

        return back()->with('success', 'Shipping rules updated.');
    }

    // ────────────────────────────────────────────────────────────
    // BUYERS & CREDIT
    // ────────────────────────────────────────────────────────────

    public function buyers(Request $r)
    {
        $q = EWBuyer::with(['user:id,name,phone','creditAccount']);
        if ($r->search)  $q->where('business_name','like',"%{$r->search}%");
        if ($r->kyb)     $q->where('kyb_status', $r->kyb);
        $buyers = $q->orderByDesc('id')->paginate(20)->withQueryString();
        return view('admin.ewholesale.buyers.index', compact('buyers'));
    }

    public function buyerKybAction(Request $r, EWBuyer $buyer)
    {
        $r->validate(['action'=>'required|in:approve,reject','note'=>'nullable|string']);
        $status = $r->action === 'approve' ? 'approved' : 'rejected';
        $buyer->update(['kyb_status'=>$status, 'approved_at'=>$status==='approved'?now():null]);
        EWActivityLog::record('buyer.kyb.'.$status, $buyer, [], ['note'=>$r->note], 'admin', auth()->id());
        return back()->with('success', "KYB {$status}.");
    }

    public function buyerCreditStore(Request $r, EWBuyer $buyer)
    {
        $r->validate([
            'credit_limit' => 'required|numeric|min:0',
            'term'         => 'required|in:net7,net15,net30',
        ]);
        EWCreditAccount::updateOrCreate(
            ['buyer_id' => $buyer->id],
            ['credit_limit' => $r->credit_limit, 'term' => $r->term, 'status' => 'active']
        );
        EWActivityLog::record('credit.upsert', $buyer, [], $r->only(['credit_limit','term']), 'admin', auth()->id());
        return back()->with('success', 'Credit account saved.');
    }

    public function buyerCreditFreeze(EWBuyer $buyer)
    {
        $acct = $buyer->creditAccount;
        $newStatus = $acct->status === 'frozen' ? 'active' : 'frozen';
        $acct->update(['status' => $newStatus]);
        EWActivityLog::record('credit.'.$newStatus, $buyer, ['status'=>$acct->status], ['status'=>$newStatus], 'admin', auth()->id());
        return back()->with('success', "Credit account {$newStatus}.");
    }

    public function buyerCreditAdjust(Request $r, EWBuyer $buyer)
    {
        $r->validate(['amount'=>'required|numeric','note'=>'required|string|max:200']);
        $acct = $buyer->creditAccount ?? abort(404);
        $this->credit->adjust($acct, (float)$r->amount, $r->note);
        return back()->with('success', 'Credit adjusted.');
    }

    public function buyerCreditLedger(EWBuyer $buyer)
    {
        $ledger = EWCreditLedger::where('credit_account_id', $buyer->creditAccount?->id)->latest()->paginate(30);
        $acct   = $buyer->creditAccount;
        return view('admin.ewholesale.buyers.ledger', compact('buyer','acct','ledger'));
    }

    public function creditAgingReport()
    {
        // Outstanding credit per buyer, grouped by age bucket
        $ledger = EWCreditLedger::selectRaw('
            credit_account_id,
            SUM(CASE WHEN due_date >= NOW() THEN amount ELSE 0 END) AS current_amount,
            SUM(CASE WHEN due_date < NOW() AND due_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN amount ELSE 0 END) AS days_30,
            SUM(CASE WHEN due_date < DATE_SUB(NOW(), INTERVAL 30 DAY) AND due_date >= DATE_SUB(NOW(), INTERVAL 60 DAY) THEN amount ELSE 0 END) AS days_60,
            SUM(CASE WHEN due_date < DATE_SUB(NOW(), INTERVAL 60 DAY) THEN amount ELSE 0 END) AS days_60_plus
        ')
        ->where('amount', '>', 0)
        ->where('type', 'charge')
        ->groupBy('credit_account_id')
        ->get();

        $rows = $ledger->map(fn($r) => [
            'account' => EWCreditAccount::with('buyer.user:id,name')->find($r->credit_account_id ?? null),
            'current' => $r->current_amount,
            '30'      => $r->days_30,
            '60'      => $r->days_60,
            '60+'     => $r->days_60_plus,
        ]);

        return view('admin.ewholesale.buyers.credit_aging', compact('rows'));
    }

    // ────────────────────────────────────────────────────────────
    // CATALOG — Categories
    // ────────────────────────────────────────────────────────────

    public function categories()
    {
        $cats = EWCategory::withCount('products')->orderBy('parent_id')->orderBy('sort_order')->get();
        return view('admin.ewholesale.catalog.categories', compact('cats'));
    }

    public function categoryStore(Request $r)
    {
        $r->validate([
            'name'       => 'required|string|max:100',
            'name_so'    => 'nullable|string|max:100',
            'parent_id'  => 'nullable|exists:ewholesale_categories,id',
            'icon'       => 'nullable|string|max:10',
            'sort_order' => 'nullable|integer',
        ]);
        $slug = Str::slug($r->name) . '-' . Str::random(4);
        EWCategory::create($r->only(['name','name_so','parent_id','icon','sort_order']) + ['slug'=>$slug]);
        Cache::forget('ew:category_tree');
        return back()->with('success', 'Category created.');
    }

    public function categoryUpdate(Request $r, EWCategory $category)
    {
        $r->validate([
            'name'       => 'required|string|max:100',
            'name_so'    => 'nullable|string|max:100',
            'icon'       => 'nullable|string|max:10',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'boolean',
        ]);
        $category->update($r->only(['name','name_so','icon','sort_order','is_active']));
        Cache::forget('ew:category_tree');
        return back()->with('success', 'Category updated.');
    }

    public function categorySortUpdate(Request $r)
    {
        // [{ id: X, sort_order: Y }]
        foreach ($r->input('items', []) as $item) {
            EWCategory::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }
        Cache::forget('ew:category_tree');
        return response()->json(['ok'=>true]);
    }

    // ────────────────────────────────────────────────────────────
    // CATALOG — Products
    // ────────────────────────────────────────────────────────────

    public function products(Request $r)
    {
        $q = EWProduct::with(['supplier:id,display_name','category:id,name']);
        if ($r->status)      $q->where('status', $r->status);
        if ($r->supplier_id) $q->where('supplier_id', $r->supplier_id);
        if ($r->category_id) $q->where('category_id', $r->category_id);
        if ($r->search)      $q->where('name','like',"%{$r->search}%");
        $products  = $q->orderByDesc('id')->paginate(30)->withQueryString();
        $suppliers = EWSupplier::active()->orderBy('display_name')->get(['id','display_name']);
        $categories= EWCategory::orderBy('name')->get(['id','name','parent_id']);
        return view('admin.ewholesale.catalog.products', compact('products','suppliers','categories'));
    }

    public function productShow(EWProduct $product)
    {
        $product->load(['supplier','category','variants','priceTiers','activeDeals']);
        return view('admin.ewholesale.catalog.product_show', compact('product'));
    }

    public function productCreate()
    {
        $suppliers  = EWSupplier::active()->orderBy('display_name')->get(['id','display_name']);
        $categories = EWCategory::active()->orderBy('name')->get(['id','name','parent_id']);
        return view('admin.ewholesale.catalog.product_form', compact('suppliers','categories'));
    }

    public function productStore(Request $r)
    {
        $r->validate(array_merge($this->productRules(), [
            'images'   => 'nullable|array|max:8',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:4096',
        ]));

        $images = [];
        foreach ($r->file('images', []) as $file) {
            $images[] = $file->store('ewholesale/products', 'public');
        }

        $product = EWProduct::create(array_merge(
            $r->only(['supplier_id','category_id','name','name_so','description','unit','units_per_pack','moq','lead_time_days','origin_country','brand','is_featured']),
            ['slug' => Str::slug($r->name) . '-' . Str::random(4), 'status' => 'pending_review', 'specs'=>$r->specs??[], 'images'=>$images]
        ));

        $this->syncVariantsAndTiers($product, $r);
        $product->syncPriceRange();

        return redirect()->route('admin.module-data.wholesale.products.show', $product)->with('success','Product created.');
    }

    public function productEdit(EWProduct $product)
    {
        $product->load(['variants','priceTiers']);
        $suppliers  = EWSupplier::active()->orderBy('display_name')->get(['id','display_name']);
        $categories = EWCategory::active()->orderBy('name')->get(['id','name','parent_id']);
        return view('admin.ewholesale.catalog.product_form', compact('product','suppliers','categories'));
    }

    public function productUpdate(Request $r, EWProduct $product)
    {
        $r->validate(array_merge($this->productRules(), [
            'images'        => 'nullable|array|max:8',
            'images.*'      => 'image|mimes:jpg,jpeg,png,webp|max:4096',
            'remove_images' => 'nullable|array',
        ]));

        // Keep existing images, remove flagged ones
        $existing = $product->images ?? [];
        $toRemove = $r->input('remove_images', []);
        $existing = array_values(array_filter($existing, fn($p) => !in_array($p, $toRemove)));
        // Delete removed files from disk
        foreach ($toRemove as $path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
        }
        // Upload new images
        foreach ($r->file('images', []) as $file) {
            $existing[] = $file->store('ewholesale/products', 'public');
        }

        $product->update(array_merge(
            $r->only(['supplier_id','category_id','name','name_so','description','unit','units_per_pack','moq','lead_time_days','origin_country','brand','is_featured']),
            ['specs' => $r->specs ?? [], 'images' => $existing]
        ));
        $this->syncVariantsAndTiers($product, $r);
        $product->syncPriceRange();
        return redirect()->route('admin.module-data.wholesale.products.show', $product)->with('success','Product updated.');
    }

    public function productStatusUpdate(Request $r, EWProduct $product)
    {
        $r->validate(['status'=>'required|in:draft,pending_review,active,rejected,archived']);
        $product->update(['status' => $r->status]);
        EWActivityLog::record('product.'.$r->status, $product, [], [], 'admin', auth()->id());
        return back()->with('success', 'Product status updated.');
    }

    private function productRules(): array
    {
        return [
            'supplier_id'     => 'required|exists:ewholesale_suppliers,id',
            'category_id'     => 'required|exists:ewholesale_categories,id',
            'name'            => 'required|string|max:200',
            'name_so'         => 'nullable|string|max:200',
            'description'     => 'nullable|string',
            'unit'            => 'required|string',
            'moq'             => 'required|numeric|min:1',
            'lead_time_days'  => 'nullable|integer|min:0',
            'origin_country'  => 'nullable|string|max:80',
            'brand'           => 'nullable|string|max:80',
            'is_featured'     => 'boolean',
            'tiers'           => 'required|array|min:1',
            'tiers.*.min_qty' => 'required|numeric|min:1',
            'tiers.*.max_qty' => 'nullable|numeric|gt:tiers.*.min_qty',
            'tiers.*.unit_price' => 'required|numeric|min:0.01',
        ];
    }

    private function syncVariantsAndTiers(EWProduct $product, Request $r): void
    {
        // Delete and re-create tiers (simplest approach)
        EWPriceTier::where('product_id', $product->id)->delete();
        foreach ($r->tiers as $tier) {
            EWPriceTier::create([
                'product_id' => $product->id,
                'variant_id' => $tier['variant_id'] ?? null,
                'min_qty'    => $tier['min_qty'],
                'max_qty'    => $tier['max_qty'] ?? null,
                'unit_price' => $tier['unit_price'],
            ]);
        }

        // Default variant if none exist
        if (!EWProductVariant::where('product_id', $product->id)->exists()) {
            EWProductVariant::create(['product_id'=>$product->id,'is_default'=>true,'is_active'=>true]);
        }
    }

    // ────────────────────────────────────────────────────────────
    // RFQ CENTER
    // ────────────────────────────────────────────────────────────

    public function rfqs(Request $r)
    {
        $q = EWRfq::with(['buyer.user:id,name','category:id,name'])->withCount('quotes');
        if ($r->status) $q->where('status', $r->status);
        $rfqs = $q->orderByDesc('id')->paginate(20)->withQueryString();

        $metrics = [
            'open'    => EWRfq::where('status','open')->count(),
            'quotes'  => EWRfqQuote::count(),
            'awarded' => EWRfq::where('status','awarded')->count(),
        ];

        return view('admin.ewholesale.rfq.index', compact('rfqs','metrics'));
    }

    public function rfqShow(EWRfq $rfq)
    {
        $rfq->load(['buyer.user','category','quotes.supplier']);
        return view('admin.ewholesale.rfq.show', compact('rfq'));
    }

    public function rfqModerate(Request $r, EWRfq $rfq)
    {
        $r->validate(['status'=>'required|in:open,closed,awarded,expired']);
        $rfq->update(['status' => $r->status]);
        return back()->with('success', 'RFQ status updated.');
    }

    // ────────────────────────────────────────────────────────────
    // ORDERS PIPELINE
    // ────────────────────────────────────────────────────────────

    public function orders(Request $r)
    {
        $q = EWOrder::with(['buyer.user:id,name','supplier:id,display_name']);
        if ($r->status)      $q->where('status', $r->status);
        if ($r->supplier_id) $q->where('supplier_id', $r->supplier_id);
        if ($r->buyer_id)    $q->where('buyer_id', $r->buyer_id);
        if ($r->search)      $q->where('order_no','like',"%{$r->search}%");
        $orders    = $q->orderByDesc('id')->paginate(20)->withQueryString();
        $suppliers = EWSupplier::active()->get(['id','display_name']);
        $statusCounts = EWOrder::selectRaw('status, COUNT(*) as cnt')->groupBy('status')
                                ->pluck('cnt','status');
        $tab = $r->tab ?? 'orders';
        if ($tab === 'disputes') {
            $disputes = EWDispute::with(['order.buyer.user:id,name','order.supplier:id,display_name'])
                ->where('status','open')->latest()->paginate(20);
            return view('admin.ewholesale.orders.index', compact('orders','suppliers','statusCounts','tab','disputes'));
        }
        return view('admin.ewholesale.orders.index', compact('orders','suppliers','statusCounts','tab'));
    }

    public function orderShow(EWOrder $order)
    {
        $order->load(['buyer.user','supplier','items.product','items.variant','payments','shipments','disputes']);
        return view('admin.ewholesale.orders.show', compact('order'));
    }

    public function orderStatusUpdate(Request $r, EWOrder $order)
    {
        $allowed = [
            'pending_confirmation' => ['confirmed','cancelled'],
            'confirmed'            => ['awaiting_payment','cancelled'],
            'awaiting_payment'     => ['processing','cancelled'],
            'processing'           => ['ready'],
            'ready'                => ['partially_shipped','shipped'],
            'partially_shipped'    => ['shipped'],
            'shipped'              => ['delivered'],
            'delivered'            => ['completed','disputed'],
        ];

        $valid = $allowed[$order->status] ?? [];
        if (!in_array($r->status, $valid)) {
            return back()->with('error', "Cannot transition from {$order->status} to {$r->status}.");
        }

        $timestamps = [];
        if ($r->status === 'confirmed')  $timestamps['confirmed_at'] = now();
        if ($r->status === 'completed')  $timestamps['completed_at'] = now();

        $order->update(array_merge(['status' => $r->status], $timestamps));
        EWActivityLog::record('order.status.'.$r->status, $order, ['status'=>$order->status], ['status'=>$r->status], 'admin', auth()->id());
        return back()->with('success', "Order moved to {$r->status}.");
    }

    public function orderPaymentRecord(Request $r, EWOrder $order)
    {
        $r->validate([
            'type'   => 'required|in:deposit,balance,full,credit_settlement,refund',
            'method' => 'required|in:wallet,evc,cash,bank,credit',
            'amount' => 'required|numeric|min:0.01',
            'ref'    => 'nullable|string|max:100',
            'note'   => 'nullable|string|max:200',
        ]);

        EWOrderPayment::create(array_merge($r->only(['type','method','amount','ref','note']), [
            'order_id'    => $order->id,
            'status'      => 'confirmed',
            'paid_at'     => now(),
            'recorded_by' => auth()->id(),
        ]));

        $order->increment('paid_total', $r->amount);
        EWActivityLog::record('order.payment', $order, [], $r->only(['type','amount']), 'admin', auth()->id());

        return back()->with('success', 'Payment recorded.');
    }

    public function orderShipmentStore(Request $r, EWOrder $order)
    {
        $r->validate([
            'items'           => 'required|array',
            'items.*.item_id' => 'required|exists:ewholesale_order_items,id',
            'items.*.qty'     => 'required|numeric|min:0.01',
            'note'            => 'nullable|string|max:200',
        ]);

        $shipNo = 'EWS-' . strtoupper(Str::random(8));
        EWShipment::create([
            'order_id'    => $order->id,
            'shipment_no' => $shipNo,
            'items'       => $r->items,
            'status'      => 'preparing',
            'note'        => $r->note,
        ]);

        // Update shipped qty per item
        foreach ($r->items as $line) {
            EWOrderItem::where('id', $line['item_id'])->increment('shipped_qty', $line['qty']);
        }

        // Check if fully shipped
        $allItems  = $order->items;
        $fullyShip = $allItems->every(fn($i) => $i->shipped_qty >= $i->qty);
        $order->update(['status' => $fullyShip ? 'shipped' : 'partially_shipped']);

        return back()->with('success', "Shipment {$shipNo} created.");
    }

    public function disputeResolve(Request $r, EWDispute $dispute)
    {
        $r->validate([
            'action' => 'required|in:resolved_refund,resolved_release,closed',
            'note'   => 'required|string|max:500',
        ]);
        $dispute->update([
            'status'          => $r->action,
            'resolution_note' => $r->note,
            'resolved_by'     => auth()->id(),
            'resolved_at'     => now(),
        ]);
        EWActivityLog::record('dispute.'.$r->action, $dispute->order, [], ['dispute_id'=>$dispute->id], 'admin', auth()->id());
        return back()->with('success', 'Dispute resolved.');
    }

    // ────────────────────────────────────────────────────────────
    // SETTINGS
    // ────────────────────────────────────────────────────────────

    public function settings()
    {
        // Ensure defaults exist
        foreach (EWSetting::defaults() as $d) {
            EWSetting::firstOrCreate(['key'=>$d['key']], $d);
        }
        $settings  = EWSetting::orderBy('key')->get();
        $priceLists= EWPriceList::all();
        $platformRule = EWShippingRule::whereNull('supplier_id')->first();
        return view('admin.ewholesale.settings', compact('settings','priceLists','platformRule'));
    }

    public function settingsUpdate(Request $r)
    {
        foreach ($r->settings ?? [] as $key => $value) {
            EWSetting::set($key, $value);
        }
        return back()->with('success', 'Settings saved.');
    }

    public function priceListStore(Request $r)
    {
        $r->validate(['name'=>'required|string|max:80','discount_percent'=>'required|numeric|min:0|max:100']);
        EWPriceList::create($r->only(['name','discount_percent']));
        return back()->with('success', 'Price list created.');
    }

    public function platformShippingUpdate(Request $r)
    {
        $r->validate([
            'basis'     => 'required|in:per_carton,per_kg,per_cbm,flat_by_zone',
            'rate'      => 'required|numeric|min:0',
            'free_over' => 'nullable|numeric|min:0',
        ]);
        EWShippingRule::updateOrCreate(
            ['supplier_id' => null],
            array_merge($r->only(['basis','rate','free_over']), ['is_active'=>true])
        );
        return back()->with('success', 'Platform shipping updated.');
    }

    // ────────────────────────────────────────────────────────────
    // REPORTS
    // ────────────────────────────────────────────────────────────

    public function reports(Request $r)
    {
        $period = $r->period ?? '30';
        $from   = now()->subDays((int)$period)->startOfDay();

        // GMV by supplier
        $gmvBySupplier = EWOrder::selectRaw('supplier_id, SUM(total) as gmv, COUNT(*) as orders')
            ->whereNotIn('status',['cancelled'])->where('created_at','>=',$from)
            ->groupBy('supplier_id')->orderByDesc('gmv')
            ->with('supplier:id,display_name')->get();

        // GMV by category
        $gmvByCategory = EWOrderItem::selectRaw('p.category_id, SUM(oi.line_total) as revenue, COUNT(DISTINCT oi.order_id) as orders')
            ->from('ewholesale_order_items as oi')
            ->join('ewholesale_products as p', 'p.id', '=', 'oi.product_id')
            ->join('ewholesale_orders as o', 'o.id', '=', 'oi.order_id')
            ->whereNotIn('o.status',['cancelled'])->where('o.created_at','>=',$from)
            ->groupBy('p.category_id')->orderByDesc('revenue')->get()
            ->map(fn($r) => array_merge($r->toArray(), ['category'=>EWCategory::find($r->category_id)]));

        // RFQ conversion
        $rfqTotal    = EWRfq::where('created_at','>=',$from)->count();
        $rfqAwarded  = EWRfq::where('status','awarded')->where('created_at','>=',$from)->count();
        $rfqConvRate = $rfqTotal ? round($rfqAwarded/$rfqTotal*100,1) : 0;

        // Dispute rate
        $totalOrders  = EWOrder::where('created_at','>=',$from)->count();
        $dispOrders   = EWDispute::where('created_at','>=',$from)->distinct('order_id')->count();
        $dispRate     = $totalOrders ? round($dispOrders/$totalOrders*100,1) : 0;

        return view('admin.ewholesale.reports', compact(
            'period','gmvBySupplier','gmvByCategory',
            'rfqTotal','rfqAwarded','rfqConvRate',
            'totalOrders','dispOrders','dispRate'
        ));
    }

    public function reportsCsvExport(Request $r)
    {
        $period = $r->period ?? '30';
        $from   = now()->subDays((int)$period)->startOfDay();
        $type   = $r->type ?? 'orders';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=ewholesale_{$type}_{$period}d.csv",
        ];

        $callback = match($type) {
            'orders' => function () use ($from) {
                $orders = EWOrder::with(['buyer.user:id,name','supplier:id,display_name'])
                    ->where('created_at','>=',$from)->get();
                echo implode(',',['Order No','Buyer','Supplier','Status','Total','Paid','Date'])."\n";
                foreach ($orders as $o) {
                    echo implode(',', [
                        $o->order_no,
                        '"'.($o->buyer->user->name??'').'"',
                        '"'.($o->supplier->display_name??'').'"',
                        $o->status,
                        $o->total,
                        $o->paid_total,
                        $o->created_at->format('Y-m-d'),
                    ])."\n";
                }
            },
            default => fn() => print("Order No,Status\n"),
        };

        return response()->stream($callback, 200, $headers);
    }

    // ────────────────────────────────────────────────────────────
    // PHASE 5: SETTLEMENT REPORT (GMV − fees − refunds per supplier)
    // ────────────────────────────────────────────────────────────

    public function settlementReport(Request $r)
    {
        $period = $r->period ?? '30';
        $from   = now()->subDays((int)$period)->startOfDay();
        $to     = now()->endOfDay();

        $rows = EWOrder::selectRaw('
                supplier_id,
                COUNT(*) as order_count,
                SUM(subtotal) as gmv,
                SUM(platform_fee) as total_fees,
                SUM(CASE WHEN status = "cancelled" THEN subtotal ELSE 0 END) as refunds,
                SUM(CASE WHEN status NOT IN ("cancelled") THEN subtotal - platform_fee ELSE 0 END) as net_payable
            ')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('supplier_id')
            ->with('supplier:id,display_name,verification')
            ->get();

        if ($r->export === 'csv') {
            $headers = [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => "attachment; filename=ew_settlement_{$period}d.csv",
            ];
            return response()->stream(function () use ($rows, $from, $to) {
                echo "Supplier,Orders,GMV,Platform Fees,Refunds,Net Payable,Period From,Period To\n";
                foreach ($rows as $row) {
                    echo implode(',', [
                        '"'.($row->supplier->display_name ?? $row->supplier_id).'"',
                        $row->order_count,
                        number_format((float)$row->gmv, 2),
                        number_format((float)$row->total_fees, 2),
                        number_format((float)$row->refunds, 2),
                        number_format((float)$row->net_payable, 2),
                        $from->toDateString(),
                        $to->toDateString(),
                    ])."\n";
                }
            }, 200, $headers);
        }

        return view('admin.ewholesale.settlement', compact('rows','period','from','to'));
    }

    // ────────────────────────────────────────────────────────────
    // PHASE 5: REVIEW MODERATION
    // ────────────────────────────────────────────────────────────

    public function reviews(Request $r)
    {
        $reviews = \App\Models\EWholesale\EWReview::with(['buyer.user:id,name','supplier:id,display_name','order:id,order_no'])
            ->when($r->supplier_id, fn($q) => $q->where('supplier_id', $r->supplier_id))
            ->when($r->visible !== null, fn($q) => $q->where('is_visible', (bool)$r->visible))
            ->latest()
            ->paginate(30)->withQueryString();
        return view('admin.ewholesale.reviews.index', compact('reviews'));
    }

    public function reviewModerate(Request $r, int $reviewId)
    {
        $r->validate([
            'action' => 'required|in:hide,show',
            'reason' => 'required_if:action,hide|nullable|string|max:255',
        ]);

        $review = \App\Models\EWholesale\EWReview::findOrFail($reviewId);

        if ($r->action === 'hide') {
            $review->update([
                'is_visible'    => false,
                'hidden_reason' => $r->reason,
                'hidden_at'     => now(),
            ]);
        } else {
            $review->update(['is_visible' => true, 'hidden_reason' => null, 'hidden_at' => null]);
        }

        return back()->with('success', 'Review moderation applied.');
    }

    // ────────────────────────────────────────────────────────────
    // PHASE 5: DISPUTE SLA VIEW & ESCALATE
    // ────────────────────────────────────────────────────────────

    public function disputes(Request $r)
    {
        $disputes = \App\Models\EWholesale\EWDispute::with(['order.buyer.user:id,name','order.supplier:id,display_name'])
            ->when($r->status, fn($q) => $q->where('status', $r->status))
            ->orderByRaw("CASE WHEN sla_deadline IS NOT NULL THEN sla_deadline ELSE '2099-01-01' END ASC")
            ->paginate(30)->withQueryString();
        return view('admin.ewholesale.disputes.index', compact('disputes'));
    }

    public function disputeEscalate(Request $r, \App\Models\EWholesale\EWDispute $dispute)
    {
        $dispute->update([
            'escalated_at' => now(),
            'escalated_by' => auth()->id(),
        ]);
        return back()->with('success', 'Dispute escalated.');
    }

    // ────────────────────────────────────────────────────────────
    // PHASE 5: CREDIT AGING — "Send Reminder" action
    // ────────────────────────────────────────────────────────────

    public function creditSendReminder(Request $r, EWCreditAccount $account)
    {
        \App\Jobs\EWholesale\SendCreditReminderJob::dispatch($account->id);
        return back()->with('success', 'Reminder dispatched to buyer.');
    }

    // ────────────────────────────────────────────────────────────
    // BANNERS
    // ────────────────────────────────────────────────────────────

    public function banners()
    {
        $banners = EWBanner::orderBy('sort_order')->get();
        return view('admin.ewholesale.banners.index', compact('banners'));
    }

    public function bannerCreate()
    {
        return view('admin.ewholesale.banners.form', ['banner' => null]);
    }

    public function bannerStore(Request $r)
    {
        $r->validate([
            'title'     => 'required|string|max:120',
            'subtitle'  => 'nullable|string|max:200',
            'cta_label' => 'nullable|string|max:40',
            'cta_url'   => 'nullable|string|max:255',
            'bg_color'  => 'nullable|string|max:20',
            'sort_order'=> 'integer|min:0',
            'is_active' => 'boolean',
            'image'     => 'nullable|image|max:4096',
        ]);

        $data = $r->only(['title','subtitle','cta_label','cta_url','bg_color','sort_order']);
        $data['is_active']  = (bool)$r->is_active;
        $data['sort_order'] = (int)($r->sort_order ?? EWBanner::max('sort_order') + 1);

        if ($r->hasFile('image')) {
            $data['image'] = $r->file('image')->store('ewholesale/banners', 'public');
        }

        EWBanner::create($data);
        Cache::forget('ew:home_payload');
        return redirect()->route('admin.module-data.wholesale.banners')->with('success', 'Banner created.');
    }

    public function bannerEdit(EWBanner $banner)
    {
        return view('admin.ewholesale.banners.form', compact('banner'));
    }

    public function bannerUpdate(Request $r, EWBanner $banner)
    {
        $r->validate([
            'title'     => 'required|string|max:120',
            'subtitle'  => 'nullable|string|max:200',
            'cta_label' => 'nullable|string|max:40',
            'cta_url'   => 'nullable|string|max:255',
            'bg_color'  => 'nullable|string|max:20',
            'sort_order'=> 'integer|min:0',
            'is_active' => 'boolean',
            'image'     => 'nullable|image|max:4096',
        ]);

        $data = $r->only(['title','subtitle','cta_label','cta_url','bg_color','sort_order']);
        $data['is_active'] = (bool)$r->is_active;

        if ($r->hasFile('image')) {
            $data['image'] = $r->file('image')->store('ewholesale/banners', 'public');
        }

        $banner->update($data);
        Cache::forget('ew:home_payload');
        return redirect()->route('admin.module-data.wholesale.banners')->with('success', 'Banner updated.');
    }

    public function bannerToggle(EWBanner $banner)
    {
        $banner->update(['is_active' => !$banner->is_active]);
        Cache::forget('ew:home_payload');
        return back()->with('success', 'Banner toggled.');
    }

    public function bannerDelete(EWBanner $banner)
    {
        $banner->delete();
        Cache::forget('ew:home_payload');
        return back()->with('success', 'Banner deleted.');
    }

    // ────────────────────────────────────────────────────────────
    // SUPPLIER CREATE / EDIT
    // ────────────────────────────────────────────────────────────

    public function supplierCreate()
    {
        $vendors = Vendor::orderBy('name')->get(['id','name']);
        return view('admin.ewholesale.suppliers.form', ['supplier' => null, 'vendors' => $vendors]);
    }

    public function supplierStore(Request $r)
    {
        $r->validate([
            'vendor_id'         => 'required|exists:vendors,id',
            'display_name'      => 'required|string|max:120',
            'about'             => 'nullable|string|max:1000',
            'warehouse_address' => 'nullable|string|max:255',
            'logo'              => 'nullable|image|max:2048',
            'verification'      => 'in:unverified,pending,verified,gold',
        ]);

        if (EWSupplier::where('vendor_id', $r->vendor_id)->exists()) {
            return back()->withErrors(['vendor_id' => 'This vendor already has a wholesale supplier profile.'])->withInput();
        }

        $data = $r->only(['vendor_id','display_name','about','warehouse_address','verification']);
        $data['is_active']    = true;
        $data['verification'] = $data['verification'] ?? 'unverified';

        if ($r->hasFile('logo')) {
            $data['logo'] = $r->file('logo')->store('ewholesale/logos', 'public');
        }

        $supplier = EWSupplier::create($data);
        EWActivityLog::record('supplier.created', $supplier, [], $data, 'admin', auth()->id());
        return redirect()->route('admin.module-data.wholesale.suppliers.show', $supplier)->with('success', 'Supplier created.');
    }

    public function supplierEdit(EWSupplier $supplier)
    {
        $vendors = Vendor::orderBy('name')->get(['id','name']);
        return view('admin.ewholesale.suppliers.form', compact('supplier','vendors'));
    }

    public function supplierUpdate(Request $r, EWSupplier $supplier)
    {
        $r->validate([
            'display_name'         => 'required|string|max:120',
            'about'                => 'nullable|string|max:1000',
            'warehouse_address'    => 'nullable|string|max:255',
            'logo'                 => 'nullable|image|max:2048',
            'verification'         => 'in:unverified,pending,verified,gold',
            'platform_fee_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $data = $r->only(['display_name','about','warehouse_address','verification','platform_fee_percent']);
        if ($r->hasFile('logo')) {
            $data['logo'] = $r->file('logo')->store('ewholesale/logos', 'public');
        }
        $supplier->update($data);
        return redirect()->route('admin.module-data.wholesale.suppliers.show', $supplier)->with('success', 'Supplier updated.');
    }

    // ────────────────────────────────────────────────────────────
    // PHASE 5: SUPPLIER SCORECARD on show page (augmented)
    // ────────────────────────────────────────────────────────────

    public function supplierScorecard(EWSupplier $supplier): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'supplier_id'          => $supplier->id,
            'rating'               => $supplier->rating,
            'response_rate'        => $supplier->response_rate,
            'response_time_avg'    => $supplier->response_time_avg,
            'on_time_delivery_rate'=> $supplier->on_time_delivery_rate,
            'dispute_rate'         => $supplier->dispute_rate,
            'cancellation_rate'    => $supplier->cancellation_rate,
            'total_orders'         => $supplier->total_orders,
            'open_disputes'        => \App\Models\EWholesale\EWDispute::whereHas('order', fn($q) => $q->where('supplier_id',$supplier->id))->where('status','open')->count(),
            'pending_orders'       => EWOrder::where('supplier_id',$supplier->id)->where('status','pending_confirmation')->count(),
        ]);
    }
}
