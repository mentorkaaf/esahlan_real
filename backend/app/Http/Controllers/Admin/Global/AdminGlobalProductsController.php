<?php

namespace App\Http\Controllers\Admin\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalProduct;
use App\Models\Global\GlobalCategory;
use App\Models\Global\GlobalProductImage;
use App\Models\Global\GlobalProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminGlobalProductsController extends Controller
{
    public function index(Request $request)
    {
        $query = GlobalProduct::with('category')->withTrashed(false);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('sku', 'like', "%{$request->search}%");
            });
        }
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $products   = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        $categories = GlobalCategory::orderBy('name')->get();

        return view('admin.global.products.index', compact('products', 'categories'));
    }

    public function create()
    {
        $categories = GlobalCategory::orderBy('name')->get();
        return view('admin.global.products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:255',
            'category_id'          => 'nullable|exists:global_categories,id',
            'description'          => 'nullable|string',
            'short_description'    => 'nullable|string|max:500',
            'price'                => 'required|numeric|min:0',
            'compare_price'        => 'nullable|numeric|min:0',
            'cost_price'           => 'nullable|numeric|min:0',
            'sku'                  => 'nullable|string|unique:global_products,sku',
            'stock'                => 'required|integer|min:0',
            'track_stock'          => 'boolean',
            'type'                 => 'required|in:physical,dropship',
            'supplier_name'        => 'nullable|string',
            'supplier_product_id'  => 'nullable|string',
            'weight_kg'            => 'nullable|numeric|min:0',
            'origin_country'       => 'nullable|string|max:2',
            'is_active'            => 'boolean',
            'is_featured'          => 'boolean',
            'is_new_arrival'       => 'boolean',
            'is_bestseller'        => 'boolean',
            'tags'                 => 'nullable|string',
            'images'               => 'nullable|array',
            'images.*'             => 'url',
            'thumbnail'            => 'nullable|string',
            'image_file'           => 'nullable|image|max:5120',
        ]);

        $data['slug']       = Str::slug($data['name']) . '-' . Str::random(5);
        $data['is_active']  = $request->boolean('is_active', true);
        $data['is_featured']= $request->boolean('is_featured');
        $data['is_new_arrival'] = $request->boolean('is_new_arrival');
        $data['is_bestseller']  = $request->boolean('is_bestseller');
        $data['track_stock']    = $request->boolean('track_stock', true);
        $data['tags']       = $request->filled('tags')
            ? array_map('trim', explode(',', $request->tags))
            : null;

        // Handle image file upload — takes priority over URL
        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('global/products', 'public');
            $data['thumbnail'] = Storage::url($path);
        }

        $images = $data['images'] ?? [];
        unset($data['images']);

        if (!isset($data['thumbnail']) && !$request->filled('thumbnail') && !empty($images)) {
            $data['thumbnail'] = $images[0];
        }

        $product = GlobalProduct::create($data);

        foreach ($images as $i => $url) {
            GlobalProductImage::create(['product_id' => $product->id, 'url' => $url, 'sort_order' => $i]);
        }

        // Variants
        if ($request->filled('variants')) {
            foreach ($request->input('variants', []) as $variant) {
                if (!empty($variant['value'])) {
                    GlobalProductVariant::create([
                        'product_id'     => $product->id,
                        'name'           => $variant['name'] ?? 'Variant',
                        'value'          => $variant['value'],
                        'price_modifier' => $variant['price_modifier'] ?? 0,
                        'stock'          => $variant['stock'] ?? 0,
                        'sku'            => $variant['sku'] ?? null,
                    ]);
                }
            }
        }

        return redirect()->route('admin.global.products.index')
            ->with('success', "Product \"{$product->name}\" created successfully.");
    }

    public function edit(GlobalProduct $product)
    {
        $product->load('images', 'variants', 'category');
        $categories = GlobalCategory::orderBy('name')->get();
        return view('admin.global.products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, GlobalProduct $product)
    {
        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'category_id'         => 'nullable|exists:global_categories,id',
            'description'         => 'nullable|string',
            'short_description'   => 'nullable|string|max:500',
            'price'               => 'required|numeric|min:0',
            'compare_price'       => 'nullable|numeric|min:0',
            'cost_price'          => 'nullable|numeric|min:0',
            'sku'                 => "nullable|string|unique:global_products,sku,{$product->id}",
            'stock'               => 'required|integer|min:0',
            'type'                => 'required|in:physical,dropship',
            'supplier_name'       => 'nullable|string',
            'supplier_product_id' => 'nullable|string',
            'weight_kg'           => 'nullable|numeric',
            'origin_country'      => 'nullable|string|max:2',
            'thumbnail'           => 'nullable|string',
            'tags'                => 'nullable|string',
            'image_file'          => 'nullable|image|max:5120',
        ]);

        $data['is_active']      = $request->boolean('is_active', true);
        $data['is_featured']    = $request->boolean('is_featured');
        $data['is_new_arrival'] = $request->boolean('is_new_arrival');
        $data['is_bestseller']  = $request->boolean('is_bestseller');
        $data['track_stock']    = $request->boolean('track_stock', true);
        $data['tags']           = $request->filled('tags')
            ? array_map('trim', explode(',', $request->tags))
            : null;

        // Handle image file upload — takes priority over URL
        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('global/products', 'public');
            $data['thumbnail'] = Storage::url($path);
        }

        $product->update($data);

        // Update images if provided
        if ($request->has('images')) {
            $product->images()->delete();
            foreach ($request->input('images', []) as $i => $url) {
                if ($url) {
                    GlobalProductImage::create(['product_id' => $product->id, 'url' => $url, 'sort_order' => $i]);
                }
            }
        }

        return redirect()->route('admin.global.products.edit', $product)
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(GlobalProduct $product)
    {
        $product->delete();
        return back()->with('success', 'Product deleted.');
    }

    public function toggleActive(GlobalProduct $product)
    {
        $product->update(['is_active' => !$product->is_active]);
        return response()->json(['is_active' => $product->is_active]);
    }
}
