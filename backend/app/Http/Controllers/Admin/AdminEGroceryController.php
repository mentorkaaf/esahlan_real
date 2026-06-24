<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Order;
use App\Models\Module;
use App\Models\District;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminEGroceryController extends Controller
{
    private function moduleId(): ?int
    {
        return Module::where('slug', 'egrocery')->value('id');
    }

    private function storeUpload($file, string $folder = 'grocery'): string
    {
        $path = $file->store($folder, 'public');
        return url('/api/v1/img/' . $path);
    }

    public function index()
    {
        $moduleId = $this->moduleId();

        $categories = Category::where('module_id', $moduleId)
            ->whereNull('vendor_id')
            ->orderBy('sort_order')->get();

        $products = Product::where('module_id', $moduleId)
            ->with('category')
            ->orderByDesc('created_at')
            ->paginate(30);

        $orders = Order::where('module_slug', 'egrocery')
            ->with(['user', 'items'])
            ->orderByDesc('created_at')
            ->paginate(20);

        $stats = [
            'total_products'  => Product::where('module_id', $moduleId)->count(),
            'active_products' => Product::where('module_id', $moduleId)->where('is_available', true)->count(),
            'total_orders'    => Order::where('module_slug', 'egrocery')->count(),
            'pending_orders'  => Order::where('module_slug', 'egrocery')->where('status', 'pending')->count(),
            'revenue'         => Order::where('module_slug', 'egrocery')->where('status', 'delivered')->sum('total_amount'),
            'today_orders'    => Order::where('module_slug', 'egrocery')->whereDate('created_at', today())->count(),
        ];

        $districts = District::orderBy('name')->get();

        return view('admin.egrocery.index', compact('categories', 'products', 'orders', 'stats', 'districts', 'moduleId'));
    }

    // ── Categories ──────────────────────────────────────────────────

    public function categoryStore(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'name_so'    => 'nullable|string|max:100',
            'image_file' => 'nullable|image|max:2048',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'nullable|boolean',
        ]);

        $cat = [
            'module_id'  => $this->moduleId(),
            'name'       => $data['name'],
            'name_so'    => $data['name_so'] ?? null,
            'slug'       => Str::slug($data['name']) . '-' . Str::random(4),
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active'  => $request->boolean('is_active', true),
        ];
        if ($request->hasFile('image_file')) $cat['image'] = $this->storeUpload($request->file('image_file'));

        Category::create($cat);
        return back()->with('success', 'Category added.');
    }

    public function categoryUpdate(Request $request, $id)
    {
        $cat = Category::findOrFail($id);
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'name_so'    => 'nullable|string|max:100',
            'image_file' => 'nullable|image|max:2048',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'nullable|boolean',
        ]);

        $update = [
            'name'       => $data['name'],
            'name_so'    => $data['name_so'] ?? $cat->name_so,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active'  => $request->boolean('is_active'),
        ];
        if ($request->hasFile('image_file')) $update['image'] = $this->storeUpload($request->file('image_file'));

        $cat->update($update);
        return back()->with('success', 'Category updated.');
    }

    public function categoryDestroy($id)
    {
        Category::findOrFail($id)->delete();
        return back()->with('success', 'Category deleted.');
    }

    // ── Products ────────────────────────────────────────────────────

    public function productStore(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:200',
            'description'    => 'nullable|string',
            'category_id'    => 'nullable|exists:categories,id',
            'price'          => 'required|numeric|min:0',
            'sale_price'     => 'nullable|numeric|min:0',
            'unit'           => 'nullable|string|max:20',
            'stock_quantity' => 'nullable|integer|min:0',
            'image_file'     => 'nullable|image|max:5120',
            'is_featured'    => 'nullable|boolean',
        ]);

        $product = [
            'module_id'      => $this->moduleId(),
            'name'           => $data['name'],
            'description'    => $data['description'] ?? null,
            'slug'           => Str::slug($data['name']) . '-' . Str::random(5),
            'category_id'    => $data['category_id'] ?? null,
            'price'          => $data['price'],
            'sale_price'     => $data['sale_price'] ?? null,
            'unit'           => $data['unit'] ?? 'piece',
            'stock_quantity' => $data['stock_quantity'] ?? 0,
            'is_available'   => true,
            'is_featured'    => $request->boolean('is_featured'),
        ];
        if ($request->hasFile('image_file')) {
            $product['thumbnail'] = $this->storeUpload($request->file('image_file'), 'grocery-products');
        }

        Product::create($product);
        return back()->with('success', 'Product added.');
    }

    public function productUpdate(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $data = $request->validate([
            'name'           => 'required|string|max:200',
            'description'    => 'nullable|string',
            'category_id'    => 'nullable|exists:categories,id',
            'price'          => 'required|numeric|min:0',
            'sale_price'     => 'nullable|numeric|min:0',
            'unit'           => 'nullable|string|max:20',
            'stock_quantity' => 'nullable|integer|min:0',
            'image_file'     => 'nullable|image|max:5120',
            'is_featured'    => 'nullable|boolean',
            'is_available'   => 'nullable|boolean',
        ]);

        $update = [
            'name'           => $data['name'],
            'description'    => $data['description'] ?? $product->description,
            'category_id'    => $data['category_id'] ?? $product->category_id,
            'price'          => $data['price'],
            'sale_price'     => $data['sale_price'],
            'unit'           => $data['unit'] ?? $product->unit,
            'stock_quantity' => $data['stock_quantity'] ?? $product->stock_quantity,
            'is_featured'    => $request->boolean('is_featured'),
            'is_available'   => $request->boolean('is_available', true),
        ];
        if ($request->hasFile('image_file')) {
            $update['thumbnail'] = $this->storeUpload($request->file('image_file'), 'grocery-products');
        }

        $product->update($update);
        return back()->with('success', 'Product updated.');
    }

    public function productDestroy($id)
    {
        Product::findOrFail($id)->delete();
        return back()->with('success', 'Product deleted.');
    }

    public function productToggle($id)
    {
        $p = Product::findOrFail($id);
        $p->update(['is_available' => !$p->is_available]);
        return back()->with('success', $p->is_available ? 'Product enabled.' : 'Product disabled.');
    }

    // ── Orders ──────────────────────────────────────────────────────

    public function orderUpdateStatus(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $request->validate(['status' => 'required|in:pending,confirmed,preparing,ready_for_pickup,out_for_delivery,delivered,cancelled']);

        $order->update([
            'status' => $request->status,
            'delivered_at' => $request->status === 'delivered' ? now() : $order->delivered_at,
        ]);

        \App\Models\OrderStatusHistory::create([
            'order_id' => $order->id,
            'status'   => $request->status,
            'note'     => 'Updated by admin',
            'changed_by' => auth()->id(),
        ]);

        // Notify customer
        try {
            $order->load('user');
            if ($order->user?->fcm_token) {
                \App\Services\FcmService::sendOrderUpdate($order->user->fcm_token, $order->order_number, $request->status, $order->id, 'egrocery');
            }
        } catch (\Throwable) {}

        return back()->with('success', 'Order status updated.');
    }
}
