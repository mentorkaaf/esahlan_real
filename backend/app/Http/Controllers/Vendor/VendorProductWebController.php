<?php
namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use App\Services\AdminAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VendorProductWebController extends Controller
{
    use HasActiveVendor;

    public function index(Request $request)
    {
        $vendor = $this->activeVendor();

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
        $vendor     = $this->activeVendor();
        $categories = Category::where('vendor_id', $vendor->id)->orWhere('module_id', $vendor->module_id)->get();
        $addons     = Addon::where('vendor_id', $vendor->id)->where('is_active', true)->orderBy('name')->get();
        $branches   = $this->getSiblingBranches($vendor);
        return view('vendor.products.create', compact('categories', 'addons', 'branches'));
    }

    public function store(Request $request)
    {
        $vendor = $this->activeVendor();

        $data = $request->validate([
            'name'           => 'required|string|max:200',
            'description'    => 'nullable|string',
            'price'          => 'required|numeric|min:0',
            'sale_price'     => 'nullable|numeric|min:0',
            'category_id'    => 'nullable|exists:categories,id',
            'sku'            => 'nullable|string|max:100',
            'stock_quantity'  => 'nullable|integer|min:0',
            'thumbnail'       => 'nullable|image|max:2048',
            'is_available'    => 'boolean',
            'available_from'  => 'nullable|date_format:H:i',
            'available_until' => 'nullable|date_format:H:i',
        ]);

        $data['vendor_id']      = $vendor->id;
        $data['module_id']      = $vendor->module_id;
        $data['slug']           = Str::slug($data['name']) . '-' . Str::random(5);
        $data['is_available']   = $request->boolean('is_available', true);
        $data['stock_quantity'] = (int) ($data['stock_quantity'] ?? 0);

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $request->file('thumbnail')->store('products', 'public');
        }

        $product = Product::create($data);
        if ($request->has('addon_ids')) {
            $product->addons()->sync($request->addon_ids);
        }

        // Copy to selected branches
        if ($request->has('branch_ids')) {
            $this->copyProductToBranches($product, $request->branch_ids, $request->addon_ids ?? []);
        }

        // Admin alert fired automatically via Product::created() model event

        return redirect()->route('vendor.products.index')->with('success', 'Product created successfully.');
    }

    public function edit(Product $product)
    {
        $vendor = $this->activeVendor();
        abort_if($product->vendor_id !== $vendor->id, 404);
        $categories = Category::where('vendor_id', $vendor->id)->orWhere('module_id', $vendor->module_id)->get();
        $addons     = Addon::where('vendor_id', $vendor->id)->where('is_active', true)->orderBy('name')->get();
        $product->load('addons');
        return view('vendor.products.edit', compact('product', 'categories', 'addons'));
    }

    public function update(Request $request, Product $product)
    {
        $vendor = $this->activeVendor();
        abort_if($product->vendor_id !== $vendor->id, 404);

        $data = $request->validate([
            'name'           => 'required|string|max:200',
            'description'    => 'nullable|string',
            'price'          => 'required|numeric|min:0',
            'sale_price'     => 'nullable|numeric|min:0',
            'category_id'    => 'nullable|exists:categories,id',
            'stock_quantity'  => 'nullable|integer|min:0',
            'thumbnail'       => 'nullable|image|max:2048',
            'is_available'    => 'boolean',
            'available_from'  => 'nullable|date_format:H:i',
            'available_until' => 'nullable|date_format:H:i',
        ]);

        $data['is_available']   = $request->boolean('is_available');
        $data['stock_quantity'] = (int) ($data['stock_quantity'] ?? 0);

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $request->file('thumbnail')->store('products', 'public');
        }

        $product->update($data);
        $product->addons()->sync($request->addon_ids ?? []);
        return redirect()->route('vendor.products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        $vendor = $this->activeVendor();
        abort_if($product->vendor_id !== $vendor->id, 404);
        $product->delete();
        return back()->with('success', 'Product deleted.');
    }

    public function toggle(Product $product)
    {
        $vendor = $this->activeVendor();
        abort_if($product->vendor_id !== $vendor->id, 404);
        $product->update(['is_available' => !$product->is_available]);
        return back()->with('success', $product->is_available ? 'Product is now available.' : 'Product is now unavailable.');
    }

    private function getSiblingBranches(Vendor $vendor): \Illuminate\Support\Collection
    {
        $userId = auth()->id();
        return Vendor::where('user_id', $userId)
            ->where('id', '!=', $vendor->id)
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);
    }

    private function copyProductToBranches(Product $product, array $branchIds, array $addonIds): void
    {
        $userId = auth()->id();
        $branches = Vendor::where('user_id', $userId)->whereIn('id', $branchIds)->get();

        foreach ($branches as $branch) {
            $copy = $product->replicate();
            $copy->vendor_id = $branch->id;
            $copy->uuid = (string) Str::uuid();
            $copy->slug = Str::slug($product->name) . '-' . Str::random(5);

            // Map category by name to branch's own category
            if ($product->category_id) {
                $branchCat = Category::where('vendor_id', $branch->id)
                    ->where('name', $product->category?->name)
                    ->first();
                $copy->category_id = $branchCat?->id ?? $product->category_id;
            }
            $copy->save();

            // Copy addons
            if (!empty($addonIds)) {
                foreach (Addon::whereIn('id', $addonIds)->get() as $addon) {
                    $branchAddon = Addon::firstOrCreate(
                        ['vendor_id' => $branch->id, 'name' => $addon->name],
                        ['price' => $addon->price, 'image' => $addon->image, 'is_active' => true]
                    );
                    \DB::table('product_addons')->insertOrIgnore([
                        'product_id' => $copy->id, 'addon_id' => $branchAddon->id,
                    ]);
                }
            }
        }
    }
}
