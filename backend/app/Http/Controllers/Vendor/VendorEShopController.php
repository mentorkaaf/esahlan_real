<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VendorEShopController extends Controller
{
    use HasActiveVendor;

    private function vendor()
    {
        $v = $this->activeVendor();
        if (!$v || $v->module_slug !== 'eshop') {
            abort(403, 'This panel is for eShop vendors only.');
        }
        return $v;
    }

    // ── Dashboard ────────────────────────────────────────────────────────────

    public function dashboard()
    {
        $vendor = $this->vendor();
        $today  = now()->toDateString();

        // Orders via order_items → products → vendor
        $orderIds = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('products.vendor_id', $vendor->id)
            ->where('orders.module_slug', 'eshop')
            ->distinct()
            ->pluck('orders.id');

        $stats = [
            'total_products'  => Product::where('vendor_id', $vendor->id)->whereNull('deleted_at')->count(),
            'active_products' => Product::where('vendor_id', $vendor->id)->where('is_available', true)->whereNull('deleted_at')->count(),
            'total_orders'    => $orderIds->count(),
            'pending_orders'  => DB::table('orders')->whereIn('id', $orderIds)->where('status', 'pending')->count(),
            'today_orders'    => DB::table('orders')->whereIn('id', $orderIds)->whereDate('created_at', $today)->count(),
            'today_revenue'   => DB::table('commissions')->where('vendor_id', $vendor->id)->whereDate('created_at', $today)->sum('vendor_earning'),
            'total_earned'    => DB::table('commissions')->where('vendor_id', $vendor->id)->where('status', 'settled')->sum('vendor_earning'),
            'pending_payout'  => DB::table('commissions')->where('vendor_id', $vendor->id)->where('status', 'pending')->sum('vendor_earning'),
            'rating'          => round($vendor->rating ?? 0, 1),
            'review_count'    => $vendor->review_count ?? 0,
        ];

        // Recent orders
        $recentOrders = DB::table('orders')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->whereIn('orders.id', $orderIds)
            ->select('orders.*', 'users.name as customer_name')
            ->orderByDesc('orders.created_at')
            ->limit(10)->get();

        // Chart: last 7 days
        $chart = DB::table('orders')
            ->whereIn('id', $orderIds)
            ->where('status', '!=', 'cancelled')
            ->whereDate('created_at', '>=', now()->subDays(6))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total_amount) as revenue')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // Top products
        $topProducts = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('products.vendor_id', $vendor->id)
            ->where('orders.module_slug', 'eshop')
            ->select('products.id', 'products.name', 'products.thumbnail',
                DB::raw('SUM(order_items.quantity) as total_qty'),
                DB::raw('SUM(order_items.total) as total_revenue'))
            ->groupBy('products.id', 'products.name', 'products.thumbnail')
            ->orderByDesc('total_qty')
            ->limit(5)->get();

        return view('vendor.eshop.dashboard', compact('vendor', 'stats', 'recentOrders', 'chart', 'topProducts'));
    }

    // ── Products ─────────────────────────────────────────────────────────────

    public function products(Request $request)
    {
        $vendor = $this->vendor();

        $products = Product::with('category')
            ->where('vendor_id', $vendor->id)
            ->whereNull('deleted_at')
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->status, fn($q) => $q->where('is_available', $request->status === 'active'))
            ->orderByDesc('created_at')
            ->paginate(20)->withQueryString();

        $categories = Category::where('vendor_id', $vendor->id)
            ->orWhere('module_id', $vendor->module_id)
            ->where('is_active', true)
            ->orderBy('name')->get();

        return view('vendor.eshop.products', compact('vendor', 'products', 'categories'));
    }

    public function productStore(Request $request)
    {
        $vendor = $this->vendor();

        $data = $request->validate([
            'name'           => 'required|string|max:200',
            'price'          => 'required|numeric|min:0',
            'sale_price'     => 'nullable|numeric|min:0',
            'category_id'    => 'nullable|exists:categories,id',
            'description'    => 'nullable|string',
            'sku'            => 'nullable|string|max:100',
            'stock_quantity' => 'nullable|integer|min:0',
            'is_available'   => 'nullable|boolean',
            'is_featured'    => 'nullable|boolean',
            'image_file'     => 'nullable|image|max:10240',
        ]);

        $thumbnail = null;
        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('eshop/products', 'public');
            $thumbnail = url('/api/v1/img/'.$path);
        }

        Product::create([
            'name'           => $data['name'],
            'slug'           => Str::slug($data['name']).'-'.Str::random(5),
            'price'          => $data['price'],
            'sale_price'     => $data['sale_price'] ?? null,
            'category_id'    => $data['category_id'] ?? null,
            'description'    => $data['description'] ?? null,
            'sku'            => $data['sku'] ?? null,
            'stock_quantity' => (int)($data['stock_quantity'] ?? 0),
            'is_available'   => $request->boolean('is_available', true),
            'is_featured'    => $request->boolean('is_featured', false),
            'thumbnail'      => $thumbnail,
            'vendor_id'      => $vendor->id,
            'module_id'      => $vendor->module_id,
        ]);

        return back()->with('success', 'Product added successfully.');
    }

    public function productUpdate(Request $request, int $id)
    {
        $vendor  = $this->vendor();
        $product = Product::where('id', $id)->where('vendor_id', $vendor->id)->firstOrFail();

        $data = $request->validate([
            'name'           => 'required|string|max:200',
            'price'          => 'required|numeric|min:0',
            'sale_price'     => 'nullable|numeric|min:0',
            'category_id'    => 'nullable|exists:categories,id',
            'description'    => 'nullable|string',
            'sku'            => 'nullable|string|max:100',
            'stock_quantity' => 'nullable|integer|min:0',
            'is_available'   => 'nullable|boolean',
            'is_featured'    => 'nullable|boolean',
            'image_file'     => 'nullable|image|max:10240',
        ]);

        $updates = [
            'name'           => $data['name'],
            'price'          => $data['price'],
            'sale_price'     => $data['sale_price'] ?? null,
            'category_id'    => $data['category_id'] ?? null,
            'description'    => $data['description'] ?? null,
            'sku'            => $data['sku'] ?? null,
            'stock_quantity' => (int)($data['stock_quantity'] ?? $product->stock_quantity),
            'is_available'   => $request->boolean('is_available', true),
            'is_featured'    => $request->boolean('is_featured', false),
        ];

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('eshop/products', 'public');
            $updates['thumbnail'] = url('/api/v1/img/'.$path);
        }

        $product->update($updates);
        return back()->with('success', 'Product updated.');
    }

    public function productDelete(int $id)
    {
        $vendor  = $this->vendor();
        $product = Product::where('id', $id)->where('vendor_id', $vendor->id)->firstOrFail();
        $product->delete();
        return back()->with('success', 'Product deleted.');
    }

    public function productToggle(int $id)
    {
        $vendor  = $this->vendor();
        $product = Product::where('id', $id)->where('vendor_id', $vendor->id)->firstOrFail();
        $product->update(['is_available' => !$product->is_available]);
        return back()->with('success', 'Product status updated.');
    }

    // ── Orders ───────────────────────────────────────────────────────────────

    public function orders(Request $request)
    {
        $vendor = $this->vendor();

        $orderIds = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('products.vendor_id', $vendor->id)
            ->where('orders.module_slug', 'eshop')
            ->when($request->status, fn($q) => $q->where('orders.status', $request->status))
            ->distinct()
            ->pluck('orders.id');

        $orders = DB::table('orders')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->whereIn('orders.id', $orderIds)
            ->select('orders.*', 'users.name as customer_name', 'users.phone as customer_phone')
            ->orderByDesc('orders.created_at')
            ->paginate(20)->withQueryString();

        // Load items per order
        $orderItemsMap = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereIn('order_items.order_id', $orderIds->toArray())
            ->where('products.vendor_id', $vendor->id)
            ->select('order_items.*', 'products.name as product_name', 'products.thumbnail')
            ->get()
            ->groupBy('order_id');

        return view('vendor.eshop.orders', compact('vendor', 'orders', 'orderItemsMap'));
    }

    // ── Store Profile ─────────────────────────────────────────────────────────

    public function store()
    {
        $vendor = $this->vendor();
        return view('vendor.eshop.store', compact('vendor'));
    }

    public function storeUpdate(Request $request)
    {
        $vendor = $this->vendor();

        $data = $request->validate([
            'name'          => 'required|string|max:150',
            'description'   => 'nullable|string|max:1000',
            'phone'         => 'nullable|string|max:30',
            'address'       => 'nullable|string|max:300',
            'delivery_fee'  => 'nullable|numeric|min:0',
            'delivery_time' => 'nullable|string|max:50',
            'minimum_order' => 'nullable|numeric|min:0',
            'logo_file'     => 'nullable|image|max:5120',
            'cover_file'    => 'nullable|image|max:10240',
        ]);

        $updates = [
            'name'          => $data['name'],
            'description'   => $data['description'] ?? $vendor->description,
            'phone'         => $data['phone'] ?? $vendor->phone,
            'address'       => $data['address'] ?? $vendor->address,
            'delivery_fee'  => $data['delivery_fee'] ?? $vendor->delivery_fee,
            'delivery_time' => $data['delivery_time'] ?? $vendor->delivery_time,
            'minimum_order' => $data['minimum_order'] ?? $vendor->minimum_order,
        ];

        if ($request->hasFile('logo_file')) {
            $path = $request->file('logo_file')->store('eshop/logos', 'public');
            $updates['logo'] = url('/api/v1/img/'.$path);
        }
        if ($request->hasFile('cover_file')) {
            $path = $request->file('cover_file')->store('eshop/covers', 'public');
            $updates['cover_image'] = url('/api/v1/img/'.$path);
        }

        DB::table('vendors')->where('id', $vendor->id)->update($updates);
        return back()->with('success', 'Store profile updated.');
    }

    // ── Earnings ──────────────────────────────────────────────────────────────

    public function earnings(Request $request)
    {
        $vendor = $this->vendor();

        $commissions = DB::table('commissions')
            ->join('orders', 'commissions.order_id', '=', 'orders.id')
            ->where('commissions.vendor_id', $vendor->id)
            ->select([
                'commissions.*',
                'orders.order_number', 'orders.total_amount',
                'orders.created_at as order_date',
            ])
            ->orderByDesc('commissions.created_at')
            ->paginate(20);

        $summary = DB::table('commissions')->where('vendor_id', $vendor->id)->selectRaw('
            SUM(CASE WHEN status="settled" THEN vendor_earning ELSE 0 END) as paid,
            SUM(CASE WHEN status="pending" THEN vendor_earning ELSE 0 END) as pending,
            SUM(commission_amount) as total_commission
        ')->first();

        $withdrawals = DB::table('withdrawal_requests')
            ->where('owner_type', 'App\\Models\\Vendor')
            ->where('owner_id', $vendor->id)
            ->orderByDesc('created_at')
            ->get();

        // Available balance = settled earnings - already-withdrawn amounts
        $totalSettled   = (float)($summary->paid ?? 0);
        $totalWithdrawn = DB::table('withdrawal_requests')
            ->where('owner_type', 'App\\Models\\Vendor')
            ->where('owner_id', $vendor->id)
            ->whereIn('status', ['pending', 'approved', 'processed'])
            ->sum('amount');
        $availableBalance = max(0, $totalSettled - $totalWithdrawn);

        return view('vendor.eshop.earnings', compact(
            'vendor', 'commissions', 'summary', 'withdrawals', 'availableBalance'
        ));
    }

    public function requestWithdrawal(Request $request)
    {
        $vendor = $this->vendor();

        $data = $request->validate([
            'amount'         => 'required|numeric|min:1',
            'method'         => 'required|in:evc_plus,zaad,bank_transfer',
            'account_number' => 'required|string|max:50',
            'account_name'   => 'required|string|max:100',
            'note'           => 'nullable|string|max:300',
        ]);

        // Recalculate available balance server-side
        $totalSettled   = (float) DB::table('commissions')
            ->where('vendor_id', $vendor->id)
            ->where('status', 'settled')
            ->sum('vendor_earning');

        $totalWithdrawn = (float) DB::table('withdrawal_requests')
            ->where('owner_type', 'App\\Models\\Vendor')
            ->where('owner_id', $vendor->id)
            ->whereIn('status', ['pending', 'approved', 'processed'])
            ->sum('amount');

        $available = max(0, $totalSettled - $totalWithdrawn);

        if ($data['amount'] > $available) {
            return back()->withErrors(['amount' => "Insufficient balance. Available: \${$available}"]);
        }

        // Get or create vendor wallet (for record-keeping)
        $wallet = DB::table('wallets')
            ->where('owner_type', 'App\\Models\\Vendor')
            ->where('owner_id', $vendor->id)
            ->first();

        if (!$wallet) {
            $walletId = DB::table('wallets')->insertGetId([
                'owner_type'    => 'App\\Models\\Vendor',
                'owner_id'      => $vendor->id,
                'balance'       => 0,
                'pending_balance'=> 0,
                'total_earned'  => 0,
                'total_withdrawn'=> 0,
                'currency'      => 'USD',
                'is_active'     => 1,
                'is_frozen'     => 0,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        } else {
            $walletId = $wallet->id;
        }

        DB::table('withdrawal_requests')->insert([
            'wallet_id'      => $walletId,
            'owner_type'     => 'App\\Models\\Vendor',
            'owner_id'       => $vendor->id,
            'amount'         => $data['amount'],
            'method'         => $data['method'],
            'payment_method' => $data['method'],
            'account_number' => $data['account_number'],
            'account_name'   => $data['account_name'],
            'note'           => $data['note'] ?? null,
            'status'         => 'pending',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return back()->with('success', 'Withdrawal request submitted successfully. Admin will process it shortly.');
    }
}
