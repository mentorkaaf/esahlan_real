<?php
namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VendorProductWebController extends Controller
{
    public function index(Request $request)
    {
        $vendor = auth()->user()->vendor;

        $products = Product::with('category')
            ->where('vendor_id', $vendor->id)
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->latest()
            ->paginate(20)->withQueryString();

        $categories = Category::where('vendor_id', $vendor->id)->orWhere('module_id', $vendor->module_id)->get();

        return view('vendor.products.index', compact('products', 'categories'));
    }

    public function create()
    {
        $vendor     = auth()->user()->vendor;
        $categories = Category::where('vendor_id', $vendor->id)->orWhere('module_id', $vendor->module_id)->get();
        return view('vendor.products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $vendor = auth()->user()->vendor;

        $data = $request->validate([
            'name'           => 'required|string|max:200',
            'description'    => 'nullable|string',
            'price'          => 'required|numeric|min:0',
            'sale_price'     => 'nullable|numeric|min:0',
            'category_id'    => 'nullable|exists:categories,id',
            'sku'            => 'nullable|string|max:100',
            'stock_quantity' => 'nullable|integer|min:0',
            'thumbnail'      => 'nullable|image|max:2048',
            'is_available'   => 'boolean',
        ]);

        $data['vendor_id'] = $vendor->id;
        $data['module_id'] = $vendor->module_id;
        $data['slug']      = Str::slug($data['name']) . '-' . Str::random(5);
        $data['is_available'] = $request->boolean('is_available', true);

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $request->file('thumbnail')->store('products', 'public');
        }

        Product::create($data);
        return redirect()->route('vendor.products.index')->with('success', 'Product created successfully.');
    }

    public function edit(Product $product)
    {
        $vendor = auth()->user()->vendor;
        abort_if($product->vendor_id !== $vendor->id, 404);
        $categories = Category::where('vendor_id', $vendor->id)->orWhere('module_id', $vendor->module_id)->get();
        return view('vendor.products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product)
    {
        $vendor = auth()->user()->vendor;
        abort_if($product->vendor_id !== $vendor->id, 404);

        $data = $request->validate([
            'name'           => 'required|string|max:200',
            'description'    => 'nullable|string',
            'price'          => 'required|numeric|min:0',
            'sale_price'     => 'nullable|numeric|min:0',
            'category_id'    => 'nullable|exists:categories,id',
            'stock_quantity' => 'nullable|integer|min:0',
            'thumbnail'      => 'nullable|image|max:2048',
            'is_available'   => 'boolean',
        ]);

        $data['is_available'] = $request->boolean('is_available');

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $request->file('thumbnail')->store('products', 'public');
        }

        $product->update($data);
        return redirect()->route('vendor.products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        $vendor = auth()->user()->vendor;
        abort_if($product->vendor_id !== $vendor->id, 404);
        $product->delete();
        return back()->with('success', 'Product deleted.');
    }

    public function toggle(Product $product)
    {
        $vendor = auth()->user()->vendor;
        abort_if($product->vendor_id !== $vendor->id, 404);
        $product->update(['is_available' => !$product->is_available]);
        return back()->with('success', $product->is_available ? 'Product is now available.' : 'Product is now unavailable.');
    }
}
