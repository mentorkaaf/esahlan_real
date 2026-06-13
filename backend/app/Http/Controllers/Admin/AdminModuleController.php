<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\District;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminModuleController extends Controller
{
    public function index()
    {
        $modules = Module::withCount('vendors')->get();
        return view('admin.modules.index', compact('modules'));
    }

    public function show(Module $module)
    {
        $module->load(['vendors', 'districts']);
        return view('admin.modules.show', compact('module'));
    }

    public function update(Request $request, Module $module)
    {
        $data = $request->validate([
            'name'               => 'sometimes|string|max:100',
            'commission_type'    => 'sometimes|in:percentage,fixed',
            'commission_value'   => 'sometimes|numeric|min:0',
            'delivery_fee'       => 'sometimes|numeric|min:0',
            'is_active'          => 'sometimes|boolean',
            'sort_order'         => 'sometimes|integer',
        ]);

        $module->update($data);
        Cache::forget('modules.active');
        return back()->with('success', 'Module updated.');
    }

    public function toggleStatus(Module $module)
    {
        $module->update(['is_active' => !$module->is_active]);
        Cache::forget('modules.active');
        return back()->with('success', 'Module status updated.');
    }

    public function updateDistricts(Request $request, Module $module)
    {
        $request->validate(['districts' => 'array', 'districts.*' => 'exists:districts,id']);
        $module->districts()->sync($request->districts ?? []);
        return back()->with('success', 'Module districts updated.');
    }
}
