<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\EWholesale\EWSupplier;
use App\Models\EWholesale\EWOrder;
use App\Models\EWholesale\EWProduct;
use App\Models\EWholesale\EWRfq;
use App\Models\EWholesale\EWRfqQuote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VendorWholesaleController extends Controller
{
    use HasActiveVendor;

    private function supplier()
    {
        $vendor = $this->activeVendor();
        if (!$vendor || $vendor->module_slug !== 'ewholesale') {
            abort(403, 'This panel is for eWholesale suppliers only.');
        }
        $supplier = EWSupplier::where('vendor_id', $vendor->id)->first();
        if (!$supplier) {
            abort(403, 'Supplier profile not found. Contact admin.');
        }
        return $supplier;
    }

    // ── Dashboard ────────────────────────────────────────────────────────────

    public function dashboard()
    {
        $supplier = $this->supplier();
        $today    = now()->toDateString();

        $stats = [
            'total_products'  => EWProduct::where('supplier_id', $supplier->id)->count(),
            'active_products' => EWProduct::where('supplier_id', $supplier->id)->where('status', 'active')->count(),
            'total_orders'    => EWOrder::where('supplier_id', $supplier->id)->count(),
            'pending_orders'  => EWOrder::where('supplier_id', $supplier->id)->where('status', 'pending_confirmation')->count(),
            'today_orders'    => EWOrder::where('supplier_id', $supplier->id)->whereDate('created_at', $today)->count(),
            'today_revenue'   => EWOrder::where('supplier_id', $supplier->id)->whereDate('created_at', $today)
                                        ->whereNotIn('status', ['cancelled'])->sum('total'),
            'total_revenue'   => EWOrder::where('supplier_id', $supplier->id)
                                        ->whereIn('status', ['completed', 'delivered'])->sum('total'),
            'pending_rfqs'    => EWRfq::where('status', 'pending')->count(), // open marketplace RFQs
        ];

        $recentOrders = EWOrder::with('buyer')
            ->where('supplier_id', $supplier->id)
            ->orderByDesc('created_at')
            ->limit(8)->get();

        return view('vendor.wholesale.dashboard', compact('supplier', 'stats', 'recentOrders'));
    }

    // ── Products ─────────────────────────────────────────────────────────────

    public function products(Request $request)
    {
        $supplier = $this->supplier();
        $q = $request->query('q');

        $products = EWProduct::with('category')
            ->where('supplier_id', $supplier->id)
            ->when($q, fn($query) => $query->where('name', 'like', "%$q%"))
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('vendor.wholesale.products', compact('supplier', 'products', 'q'));
    }

    public function productToggle(int $id)
    {
        $supplier = $this->supplier();
        $product  = EWProduct::where('id', $id)->where('supplier_id', $supplier->id)->firstOrFail();
        $product->update(['status' => $product->status === 'active' ? 'archived' : 'active']);
        return back()->with('success', 'Product status updated.');
    }

    // ── Orders ───────────────────────────────────────────────────────────────

    public function orders(Request $request)
    {
        $supplier = $this->supplier();
        $status   = $request->query('status', 'all');

        $orders = EWOrder::with('buyer')
            ->where('supplier_id', $supplier->id)
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20);

        $statusCounts = EWOrder::where('supplier_id', $supplier->id)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('vendor.wholesale.orders', compact('supplier', 'orders', 'status', 'statusCounts'));
    }

    public function orderShow(int $id)
    {
        $supplier = $this->supplier();
        $order    = EWOrder::with(['buyer', 'items.product'])
            ->where('id', $id)->where('supplier_id', $supplier->id)->firstOrFail();

        return view('vendor.wholesale.order_show', compact('supplier', 'order'));
    }

    public function orderStatus(Request $request, int $id)
    {
        $supplier = $this->supplier();
        $order    = EWOrder::where('id', $id)->where('supplier_id', $supplier->id)->firstOrFail();

        $allowed = ['confirmed', 'processing', 'ready', 'shipped', 'delivered', 'completed', 'cancelled'];
        $request->validate(['status' => 'required|in:' . implode(',', $allowed)]);

        $data = ['status' => $request->status];
        if ($request->status === 'confirmed')  $data['confirmed_at'] = now();
        if ($request->status === 'completed')  $data['completed_at'] = now();

        $order->update($data);
        return back()->with('success', 'Order status updated.');
    }

    // ── RFQs ─────────────────────────────────────────────────────────────────

    public function rfqs(Request $request)
    {
        $supplier = $this->supplier();
        $tab      = $request->query('tab', 'open'); // open | my_quotes

        if ($tab === 'my_quotes') {
            // RFQs this supplier has already quoted on
            $quotedRfqIds = EWRfqQuote::where('supplier_id', $supplier->id)->pluck('rfq_id');
            $rfqs = EWRfq::with(['buyer', 'category'])
                ->whereIn('id', $quotedRfqIds)
                ->orderByDesc('created_at')
                ->paginate(20);
        } else {
            // Open RFQs anyone can respond to
            $rfqs = EWRfq::with(['buyer', 'category'])
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->paginate(20);
        }

        return view('vendor.wholesale.rfqs', compact('supplier', 'rfqs', 'tab'));
    }

    // ── Store Profile ────────────────────────────────────────────────────────

    public function store()
    {
        $supplier = $this->supplier();
        return view('vendor.wholesale.store', compact('supplier'));
    }

    public function storeUpdate(Request $request)
    {
        $supplier = $this->supplier();

        $data = $request->validate([
            'display_name' => 'required|string|max:120',
            'tagline'      => 'nullable|string|max:200',
            'description'  => 'nullable|string',
            'phone'        => 'nullable|string|max:30',
            'address'      => 'nullable|string|max:255',
            'min_order_value' => 'nullable|numeric|min:0',
        ]);

        if ($request->hasFile('logo')) {
            $logo = $request->file('logo')->store('ewholesale/logos', 'public');
            if ($supplier->logo) Storage::disk('public')->delete($supplier->logo);
            $data['logo'] = $logo;
        }

        $supplier->update($data);
        return back()->with('success', 'Store profile updated.');
    }
}
