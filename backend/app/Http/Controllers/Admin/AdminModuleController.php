<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\District;
use App\Models\User;
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
        Cache::forget('modules.active.public');
        $this->_broadcastModulesChanged();
        return back()->with('success', 'Module updated.');
    }

    public function toggleStatus(Module $module)
    {
        $module->update(['is_active' => !$module->is_active]);
        Cache::forget('modules.active');
        Cache::forget('modules.active.public');
        $this->_broadcastModulesChanged();
        return back()->with('success', 'Module status updated.');
    }

    private function _broadcastModulesChanged(): void
    {
        try {
            $modules = Module::where('is_active', true)->orderBy('sort_order')->get(['id', 'slug', 'name']);
            event(new \App\Events\ModulesUpdated($modules->toArray()));
        } catch (\Throwable) {}
    }

    public function setVisibility(Request $request, Module $module)
    {
        $request->validate(['visibility' => 'required|in:public,private']);
        $module->update(['visibility' => $request->visibility]);
        Cache::forget('modules.active.public');
        Cache::forget('modules.active');
        $this->_broadcastModulesChanged();
        return back()->with('success', 'Module visibility updated.');
    }

    public function betaUsers(Module $module)
    {
        $users = $module->betaUsers()->select('users.id', 'users.name', 'users.email')->get();
        return response()->json(['success' => true, 'data' => $users]);
    }

    public function addBetaUser(Request $request, Module $module)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);
        $module->betaUsers()->syncWithoutDetaching([$request->user_id]);
        return response()->json(['success' => true, 'message' => 'User added to beta access.']);
    }

    public function removeBetaUser(Request $request, Module $module, int $userId)
    {
        $module->betaUsers()->detach($userId);
        return response()->json(['success' => true, 'message' => 'User removed from beta access.']);
    }

    public function searchUsers(Request $request)
    {
        $q = $request->get('q', '');
        $users = User::where(function($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%")
                      ->orWhere('phone', 'like', "%{$q}%");
            })
            ->whereHas('role', fn($r) => $r->where('slug', 'customer'))
            ->select('id', 'name', 'email', 'phone')
            ->limit(10)
            ->get();
        return response()->json(['success' => true, 'data' => $users]);
    }

    public function updateDistricts(Request $request, Module $module)
    {
        $request->validate(['districts' => 'array', 'districts.*' => 'exists:districts,id']);
        $module->districts()->sync($request->districts ?? []);
        return back()->with('success', 'Module districts updated.');
    }
}
