<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ModuleRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HrModuleRoleController extends Controller
{
    /**
     * List all module roles, grouped by module.
     */
    public function index(Request $request)
    {
        $moduleId = $request->module_id;

        $query = ModuleRole::with('module')
            ->withCount('workforceAssignments as employees_count')
            ->orderBy('module_id')
            ->orderBy('sort_order');

        if ($moduleId) {
            $query->where('module_id', $moduleId);
        }

        $roles = $query->get()->groupBy('module_id');

        $modules = Module::orderBy('sort_order')->get();
        $selectedModule = $moduleId ? $modules->firstWhere('id', $moduleId) : null;

        return view('hr.module-roles.index', compact('roles', 'modules', 'selectedModule'));
    }

    /**
     * Show a single module role with its permissions.
     */
    public function show(ModuleRole $moduleRole)
    {
        $moduleRole->load('module', 'permissions', 'workforceAssignments.employee');

        // Group permissions by their group column
        $permsByGroup = $moduleRole->permissions->groupBy('group');

        $groups = ModuleRole::permissionGroups();

        return view('hr.module-roles.show', compact('moduleRole', 'permsByGroup', 'groups'));
    }

    /**
     * Show create form.
     */
    public function create(Request $request)
    {
        $modules = Module::where('is_active', true)->orderBy('sort_order')->get();
        $selectedModule = $request->module_id ? Module::find($request->module_id) : null;
        $groups = ModuleRole::permissionGroups();

        // All permissions for the selected module
        $availablePermissions = $selectedModule
            ? DB::table('permissions')
                ->where('module', $selectedModule->slug)
                ->orderByRaw("FIELD(\"group\", 'dashboard','employees','orders','vendors','customers','reports','settings','finance','operations')")
                ->get()
                ->groupBy('group')
            : collect();

        return view('hr.module-roles.create', compact(
            'modules', 'selectedModule', 'groups', 'availablePermissions'
        ));
    }

    /**
     * Store a new module role.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'module_id'      => ['required', 'exists:modules,id'],
            'name'           => ['required', 'string', 'max:120'],
            'description'    => ['nullable', 'string', 'max:500'],
            'is_default'     => ['nullable', 'boolean'],
            'sort_order'     => ['nullable', 'integer', 'min:0'],
            'permissions'    => ['nullable', 'array'],
            'permissions.*'  => ['integer', 'exists:permissions,id'],
        ]);

        // Auto-generate slug from module slug + role name
        $module = Module::findOrFail($data['module_id']);
        $slug   = Str::slug("{$module->slug} {$data['name']}", '_');

        // Ensure unique slug
        if (ModuleRole::where('slug', $slug)->exists()) {
            $slug .= '_' . substr(uniqid(), -4);
        }

        // Uniqueness check: same name + module
        if (ModuleRole::where('module_id', $data['module_id'])->where('name', $data['name'])->exists()) {
            return back()->withErrors(['name' => 'A role with this name already exists in this module.'])->withInput();
        }

        DB::transaction(function () use ($data, $slug, &$role) {
            $role = ModuleRole::create([
                'module_id'   => $data['module_id'],
                'name'        => $data['name'],
                'slug'        => $slug,
                'description' => $data['description'] ?? null,
                'is_default'  => !empty($data['is_default']),
                'is_system'   => false,
                'status'      => 'active',
                'sort_order'  => $data['sort_order'] ?? 0,
            ]);

            if (!empty($data['permissions'])) {
                $pairs = array_map(fn($pid) => [
                    'module_role_id' => $role->id,
                    'permission_id'  => $pid,
                ], $data['permissions']);
                DB::table('module_role_permissions')->insertOrIgnore($pairs);
            }
        });

        return redirect()
            ->route('hr.module-roles.show', $role)
            ->with('success', "Module role \"{$role->name}\" created successfully.");
    }

    /**
     * Show edit form.
     */
    public function edit(ModuleRole $moduleRole)
    {
        $moduleRole->load('module', 'permissions');
        $groups = ModuleRole::permissionGroups();

        $availablePermissions = DB::table('permissions')
            ->where('module', $moduleRole->module->slug)
            ->orderByRaw("FIELD(`group`, 'dashboard','employees','orders','vendors','customers','reports','settings','finance','operations')")
            ->get()
            ->groupBy('group');

        $grantedIds = $moduleRole->permissions->pluck('id')->flip()->all();

        return view('hr.module-roles.edit', compact(
            'moduleRole', 'groups', 'availablePermissions', 'grantedIds'
        ));
    }

    /**
     * Update a module role.
     */
    public function update(Request $request, ModuleRole $moduleRole)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:120'],
            'description'   => ['nullable', 'string', 'max:500'],
            'is_default'    => ['nullable', 'boolean'],
            'sort_order'    => ['nullable', 'integer', 'min:0'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
            'status'        => ['nullable', 'in:active,archived'],
        ]);

        // Uniqueness check: same name + module (excluding self)
        $dupName = ModuleRole::where('module_id', $moduleRole->module_id)
            ->where('name', $data['name'])
            ->where('id', '!=', $moduleRole->id)
            ->exists();

        if ($dupName) {
            return back()->withErrors(['name' => 'A role with this name already exists in this module.'])->withInput();
        }

        DB::transaction(function () use ($data, $moduleRole) {
            $moduleRole->update([
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'is_default'  => !empty($data['is_default']),
                'sort_order'  => $data['sort_order'] ?? $moduleRole->sort_order,
                'status'      => $data['status'] ?? $moduleRole->status,
            ]);

            // Sync permissions (delete all, re-insert selected)
            DB::table('module_role_permissions')
                ->where('module_role_id', $moduleRole->id)
                ->delete();

            if (!empty($data['permissions'])) {
                $pairs = array_map(fn($pid) => [
                    'module_role_id' => $moduleRole->id,
                    'permission_id'  => $pid,
                ], $data['permissions']);
                DB::table('module_role_permissions')->insert($pairs);
            }
        });

        return redirect()
            ->route('hr.module-roles.show', $moduleRole)
            ->with('success', "Module role \"{$moduleRole->name}\" updated successfully.");
    }

    /**
     * Archive or restore a module role.
     */
    public function toggleStatus(ModuleRole $moduleRole)
    {
        if ($moduleRole->is_system) {
            return back()->withErrors(['status' => 'System roles cannot be archived.']);
        }

        $moduleRole->update([
            'status' => $moduleRole->status === 'active' ? 'archived' : 'active',
        ]);

        return back()->with('success', 'Role status updated.');
    }

    /**
     * JSON endpoint — get roles for a module (used by assign form picker).
     */
    public function byModule(Module $module)
    {
        $roles = ModuleRole::where('module_id', $module->id)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'description', 'is_default']);

        return response()->json($roles);
    }

    /**
     * View an employee's resolved permissions for a module.
     */
    public function employeePermissions(\App\Models\HR\HrEmployee $employee)
    {
        $assignments = $employee->workforceAssignments()
            ->with(['module', 'moduleRole.permissions'])
            ->where('status', 'active')
            ->get();

        $groups = ModuleRole::permissionGroups();

        return view('hr.module-roles.employee-permissions', compact('employee', 'assignments', 'groups'));
    }
}
