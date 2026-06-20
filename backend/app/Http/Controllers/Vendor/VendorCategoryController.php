<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VendorCategoryController extends Controller
{
    use HasActiveVendor;

    public function index()
    {
        $vendor = $this->activeVendor();
        $categories = Category::where('vendor_id', $vendor->id)
            ->orderBy('sort_order')
            ->get();

        return view('vendor.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $vendor = $this->activeVendor();

        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'image_file' => 'nullable|image|max:2048',
            'sort_order'  => 'nullable|integer',
            'is_active'   => 'nullable|boolean',
        ]);

        $cat = [
            'vendor_id' => $vendor->id,
            'module_id' => $vendor->module_id,
            'name'      => $data['name'],
            'slug'      => Str::slug($data['name']) . '-' . Str::random(4),
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active'  => $request->boolean('is_active', true),
        ];

        if ($request->hasFile('image_file')) {
            $cat['image'] = $this->storeImage($request->file('image_file'));
        }

        Category::create($cat);
        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, Category $category)
    {
        $vendor = $this->activeVendor();
        abort_if($category->vendor_id !== $vendor->id, 404);

        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'image_file' => 'nullable|image|max:2048',
            'sort_order'  => 'nullable|integer',
            'is_active'   => 'nullable|boolean',
        ]);

        $update = [
            'name'       => $data['name'],
            'slug'       => Str::slug($data['name']) . '-' . Str::random(4),
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active'  => $request->boolean('is_active', true),
        ];

        if ($request->hasFile('image_file')) {
            $update['image'] = $this->storeImage($request->file('image_file'));
        }

        $category->update($update);
        return back()->with('success', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        $vendor = $this->activeVendor();
        abort_if($category->vendor_id !== $vendor->id, 404);
        $category->delete();
        return back()->with('success', 'Category deleted.');
    }

    private function storeImage($file): string
    {
        $path = $file->store('categories', 'public');
        return url('/api/v1/img/' . $path);
    }
}
