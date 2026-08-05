<?php

namespace App\Http\Controllers\Admin\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminGlobalCategoriesController extends Controller
{
    public function index()
    {
        $categories = GlobalCategory::withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.global.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'icon'       => 'nullable|string|max:10',
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'nullable|boolean',
        ]);

        $data['slug']       = Str::slug($data['name']) . '-' . Str::random(4);
        $data['is_active']  = $request->boolean('is_active', true);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        GlobalCategory::create($data);

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, GlobalCategory $category)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'icon'       => 'nullable|string|max:10',
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'nullable|boolean',
        ]);

        $data['is_active']  = $request->boolean('is_active', true);
        $data['sort_order'] = $data['sort_order'] ?? $category->sort_order;

        $category->update($data);

        return back()->with('success', 'Category updated.');
    }

    public function destroy(GlobalCategory $category)
    {
        if ($category->products()->count() > 0) {
            return back()->with('error', 'Cannot delete category with products. Move products first.');
        }
        $category->delete();
        return back()->with('success', 'Category deleted.');
    }

    public function toggle(GlobalCategory $category)
    {
        $category->update(['is_active' => !$category->is_active]);
        return back()->with('success', $category->is_active ? 'Category activated.' : 'Category deactivated.');
    }
}
