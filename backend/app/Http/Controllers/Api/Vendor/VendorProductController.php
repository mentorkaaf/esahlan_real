<?php
namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VendorProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendor = auth()->user()->vendor;
        $products = Product::with(['category', 'images', 'variants'])
            ->where('vendor_id', $vendor->id)
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->paginated($products, fn($p) => new ProductResource($p));
    }

    public function store(Request $request): JsonResponse
    {
        $vendor = auth()->user()->vendor;
        $data = $request->validate([
            'name'           => 'required|string|max:200',
            'name_so'        => 'nullable|string|max:200',
            'description'    => 'nullable|string',
            'price'          => 'required|numeric|min:0',
            'sale_price'     => 'nullable|numeric|min:0|lt:price',
            'category_id'    => 'required|exists:categories,id',
            'sku'            => 'nullable|string|max:100',
            'stock_quantity' => 'nullable|integer|min:0',
            'thumbnail'      => 'nullable|image|max:2048',
            'is_available'   => 'boolean',
        ]);

        $data['vendor_id'] = $vendor->id;
        $data['module_id'] = $vendor->module_id;
        $data['slug'] = Str::slug($data['name']) . '-' . Str::random(5);

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $request->file('thumbnail')->store('products', 'public');
        }

        $product = Product::create($data);

        // Handle multiple images
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $i => $img) {
                $path = $img->store('products', 'public');
                $product->images()->create([
                    'image'      => $path,
                    'sort_order' => $i + 1,
                    'is_primary' => $i === 0,
                ]);
            }
        }

        return $this->success(new ProductResource($product->load('images')), 'Product created.', 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $vendor = auth()->user()->vendor;
        if ($product->vendor_id !== $vendor->id) {
            return $this->error('Product not found.', 404);
        }

        $data = $request->validate([
            'name'           => 'sometimes|string|max:200',
            'name_so'        => 'nullable|string|max:200',
            'description'    => 'nullable|string',
            'price'          => 'sometimes|numeric|min:0',
            'sale_price'     => 'nullable|numeric|min:0',
            'category_id'    => 'sometimes|exists:categories,id',
            'stock_quantity' => 'nullable|integer|min:0',
            'is_available'   => 'boolean',
        ]);

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $request->file('thumbnail')->store('products', 'public');
        }

        $product->update($data);
        return $this->success(new ProductResource($product->fresh()->load('images', 'variants')), 'Product updated.');
    }

    public function destroy(Product $product): JsonResponse
    {
        $vendor = auth()->user()->vendor;
        if ($product->vendor_id !== $vendor->id) {
            return $this->error('Product not found.', 404);
        }
        $product->delete();
        return $this->success(null, 'Product deleted.');
    }

    public function toggleAvailability(Product $product): JsonResponse
    {
        $vendor = auth()->user()->vendor;
        if ($product->vendor_id !== $vendor->id) {
            return $this->error('Product not found.', 404);
        }
        $product->update(['is_available' => !$product->is_available]);
        return $this->success(['is_available' => $product->is_available]);
    }
}
