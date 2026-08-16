<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrEmployee;
use App\Models\HR\WorkforceAssignment;
use App\Models\Module;
use App\Models\ModuleDepartment;
use App\Services\HR\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class HrModuleDepartmentController extends Controller
{
    /**
     * List all module departments, optionally filtered by module.
     */
    public function index(Request $request)
    {
        $modules   = Module::orderBy('sort_order')->get();
        $moduleId  = $request->integer('module_id') ?: null;
        $status    = $request->input('status', 'active');

        $query = ModuleDepartment::with(['module', 'manager'])
            ->withCount(['activeAssignments'])
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($moduleId) {
            $query->where('module_id', $moduleId);
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $departments = $query->get();
        $selectedModule = $moduleId ? Module::find($moduleId) : null;

        return view('hr.module-departments.index', compact(
            'departments', 'modules', 'selectedModule', 'status'
        ));
    }

    /**
     * Show create form.
     */
    public function create(Request $request)
    {
        $modules   = Module::where('is_active', true)->orderBy('sort_order')->get();
        $employees = HrEmployee::whereIn('status', ['active', 'probation'])
            ->orderBy('first_name')->get();
        $selectedModule = $request->module_id ? Module::find($request->module_id) : null;

        return view('hr.module-departments.create', compact('modules', 'employees', 'selectedModule'));
    }

    /**
     * Store a new module department.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'module_id'   => ['required', 'exists:modules,id'],
            'name'        => [
                'required', 'string', 'max:120',
                Rule::unique('module_departments')->where(fn($q) => $q->where('module_id', $request->module_id)),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'manager_id'  => ['nullable', 'exists:hr_employees,id'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ]);

        $dept = ModuleDepartment::create([
            'module_id'   => $data['module_id'],
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'manager_id'  => $data['manager_id'] ?? null,
            'status'      => 'active',
            'sort_order'  => $data['sort_order'] ?? 0,
        ]);

        AuditService::log('module_dept.created', $dept, null, [
            'module' => $dept->module?->slug,
            'name'   => $dept->name,
        ]);

        return redirect()
            ->route('hr.module-departments.show', $dept)
            ->with('success', "Department \"{$dept->name}\" created.");
    }

    /**
     * Show department detail — employees, positions.
     */
    public function show(ModuleDepartment $moduleDepartment)
    {
        $moduleDepartment->load([
            'module',
            'manager.position',
            'manager.department',
        ]);

        $assignments = WorkforceAssignment::with(['employee.position', 'employee.department'])
            ->where('module_department_id', $moduleDepartment->id)
            ->orderBy('status')
            ->orderBy('assigned_at')
            ->get();

        // Employees eligible to move into this dept (same module, no dept yet or different dept)
        $availableEmployees = WorkforceAssignment::with('employee')
            ->where('module_id', $moduleDepartment->module_id)
            ->where('status', 'active')
            ->where(fn($q) => $q->whereNull('module_department_id')
                                ->orWhere('module_department_id', '!=', $moduleDepartment->id))
            ->get()
            ->pluck('employee')
            ->filter()
            ->unique('id');

        // Positions represented in this department
        $positions = $assignments
            ->map(fn($a) => $a->employee?->position)
            ->filter()
            ->unique('id')
            ->values();

        $activeEmployees = HrEmployee::whereIn('status', ['active', 'probation'])
            ->orderBy('first_name')->get();

        return view('hr.module-departments.show', compact(
            'moduleDepartment', 'assignments', 'availableEmployees', 'positions', 'activeEmployees'
        ));
    }

    /**
     * Show edit form.
     */
    public function edit(ModuleDepartment $moduleDepartment)
    {
        $modules   = Module::where('is_active', true)->orderBy('sort_order')->get();
        $employees = HrEmployee::whereIn('status', ['active', 'probation'])
            ->orderBy('first_name')->get();

        return view('hr.module-departments.edit', compact('moduleDepartment', 'modules', 'employees'));
    }

    /**
     * Update a department.
     */
    public function update(Request $request, ModuleDepartment $moduleDepartment)
    {
        $data = $request->validate([
            'name'        => [
                'required', 'string', 'max:120',
                Rule::unique('module_departments')
                    ->where(fn($q) => $q->where('module_id', $moduleDepartment->module_id))
                    ->ignore($moduleDepartment->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'manager_id'  => ['nullable', 'exists:hr_employees,id'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ]);

        $before = $moduleDepartment->only('name', 'description', 'manager_id', 'sort_order');

        $moduleDepartment->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'manager_id'  => $data['manager_id'] ?? null,
            'sort_order'  => $data['sort_order'] ?? $moduleDepartment->sort_order,
        ]);

        AuditService::log('module_dept.updated', $moduleDepartment, $before, $moduleDepartment->only('name', 'manager_id'));

        return redirect()
            ->route('hr.module-departments.show', $moduleDepartment)
            ->with('success', 'Department updated.');
    }

    /**
     * Change department status: activate / deactivate / archive.
     */
    public function status(Request $request, ModuleDepartment $moduleDepartment)
    {
        $newStatus = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],
        ])['status'];

        if ($moduleDepartment->isArchived()) {
            return back()->withErrors(['status' => 'Archived departments cannot be changed. Please create a new department.']);
        }

        $before = $moduleDepartment->only('status');
        $moduleDepartment->update(['status' => $newStatus]);
        AuditService::log('module_dept.status_changed', $moduleDepartment, $before, ['status' => $newStatus]);

        $labels = ['active' => 'activated', 'inactive' => 'deactivated', 'archived' => 'archived'];
        return back()->with('success', "Department {$labels[$newStatus]}.");
    }

    /**
     * Assign manager to department.
     */
    public function assignManager(Request $request, ModuleDepartment $moduleDepartment)
    {
        $data = $request->validate([
            'manager_id' => ['required', 'exists:hr_employees,id'],
        ]);

        $before = $moduleDepartment->only('manager_id');
        $moduleDepartment->update(['manager_id' => $data['manager_id']]);
        AuditService::log('module_dept.manager_assigned', $moduleDepartment, $before, ['manager_id' => $data['manager_id']]);

        return back()->with('success', 'Department manager updated.');
    }

    /**
     * Move an assignment's department (reassign employee to this dept).
     */
    public function addEmployee(Request $request, ModuleDepartment $moduleDepartment)
    {
        $data = $request->validate([
            'assignment_id' => ['required', 'exists:workforce_assignments,id'],
        ]);

        $assignment = WorkforceAssignment::where('id', $data['assignment_id'])
            ->where('module_id', $moduleDepartment->module_id)
            ->where('status', 'active')
            ->firstOrFail();

        $before = ['module_department_id' => $assignment->module_department_id];
        $assignment->update(['module_department_id' => $moduleDepartment->id]);
        AuditService::log('module_dept.employee_added', $moduleDepartment, $before, [
            'employee_id' => $assignment->employee_id,
        ]);

        return back()->with('success', 'Employee added to department.');
    }

    /**
     * JSON: departments for a module (for dynamic picker in assign form).
     */
    public function byModule(Module $module)
    {
        $depts = ModuleDepartment::where('module_id', $module->id)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        return response()->json($depts);
    }

    /**
     * Remove an employee from this department (set dept to null).
     */
    public function removeEmployee(WorkforceAssignment $assignment)
    {
        $dept   = $assignment->moduleDepartment;
        $before = ['module_department_id' => $assignment->module_department_id];
        $assignment->update(['module_department_id' => null]);

        if ($dept) {
            AuditService::log('module_dept.employee_removed', $dept, $before, [
                'employee_id' => $assignment->employee_id,
            ]);
        }

        return back()->with('success', 'Employee removed from department.');
    }
}
