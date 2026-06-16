<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class AdminAccessController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search'));

        $users = User::with(['role', 'managedModules'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('name', 'like', "%$search%")
                      ->orWhere('email', 'like', "%$search%")
                      ->orWhere('phone', 'like', "%$search%");
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $roles   = Role::orderBy('name')->get();
        $modules = Module::orderBy('sort_order')->get();

        return view('admin.access.index', compact('users', 'roles', 'modules', 'search'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'role_id'   => 'required|exists:roles,id',
            'modules'   => 'nullable|array',
            'modules.*' => 'integer|exists:modules,id',
        ]);

        $role = Role::findOrFail($data['role_id']);

        // Don't let an admin lock themselves out of full access.
        if ($user->id === auth()->id() && auth()->user()->isFullAdmin() && $role->slug !== auth()->user()->role->slug) {
            return back()->withErrors(['role_id' => 'You cannot change your own role.']);
        }

        $user->role_id = $role->id;
        $user->save();

        // Module assignments only apply to employees.
        if ($role->slug === 'employee') {
            $user->managedModules()->sync($data['modules'] ?? []);
        } else {
            $user->managedModules()->detach();
        }

        return back()->with('success', "Access updated for {$user->name}.");
    }
}
