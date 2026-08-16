<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrEmployee;
use App\Models\HR\WorkforceAssignment;
use App\Models\Module;
use App\Services\HR\WorkforceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HrWorkforceController extends Controller
{
    /**
     * Workforce overview — all modules with employee counts.
     */
    public function index()
    {
        $modules = Module::orderBy('sort_order')
            ->withCount([
                'workforceAssignments as total_assigned' => fn($q) => $q->where('status', 'active'),
            ])
            ->get();

        // Attach the assignment records grouped by module for quick display
        $recentAssignments = WorkforceAssignment::with(['employee', 'module'])
            ->where('status', 'active')
            ->orderByDesc('assigned_at')
            ->limit(10)
            ->get();

        return view('hr.workforce.index', compact('modules', 'recentAssignments'));
    }

    /**
     * All employees assigned to a specific module.
     */
    public function module(Module $module)
    {
        $assignments = WorkforceService::listByModule($module->id);

        $availableEmployees = HrEmployee::whereIn('status', ['active', 'probation'])
            ->whereDoesntHave('workforceAssignments', fn($q) =>
                $q->where('module_id', $module->id)->where('status', 'active'))
            ->orderBy('first_name')
            ->get();

        return view('hr.workforce.module', compact('module', 'assignments', 'availableEmployees'));
    }

    /**
     * Show assign form.
     */
    public function create(Request $request)
    {
        $employees = HrEmployee::whereIn('status', ['active', 'probation'])
            ->orderBy('first_name')->get();

        $modules = Module::where('is_active', true)->orderBy('sort_order')->get();

        $selectedEmployee = $request->employee_id
            ? HrEmployee::find($request->employee_id)
            : null;

        $selectedModule = $request->module_id
            ? Module::find($request->module_id)
            : null;

        // Departments for the selected module (for the department picker)
        $moduleDepartments = $selectedModule
            ? \App\Models\ModuleDepartment::where('module_id', $selectedModule->id)
                ->where('status', 'active')
                ->orderBy('sort_order')->get()
            : collect();

        return view('hr.workforce.assign', compact('employees', 'modules', 'selectedEmployee', 'selectedModule', 'moduleDepartments'));
    }

    /**
     * Store a new workforce assignment.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'          => ['required', 'exists:hr_employees,id'],
            'module_id'            => ['required', 'exists:modules,id'],
            'module_department_id' => ['nullable', 'exists:module_departments,id'],
            'role_in_module'       => ['nullable', 'string', 'max:60'],
            'notes'                => ['nullable', 'string', 'max:500'],
        ]);

        $employee = HrEmployee::findOrFail($data['employee_id']);
        $module   = Module::findOrFail($data['module_id']);

        $result = WorkforceService::assign(
            $employee,
            $module,
            $data['role_in_module'] ?? 'staff',
            $data['notes'] ?? null,
            !empty($data['module_department_id']) ? (int) $data['module_department_id'] : null,
        );

        if ($result['error']) {
            return back()->withErrors(['assign' => $result['error']])->withInput();
        }

        return redirect()
            ->route('hr.workforce.module', $module)
            ->with('success', "{$employee->full_name} assigned to {$module->name} successfully.");
    }

    /**
     * End an assignment (unassign).
     */
    public function destroy(WorkforceAssignment $assignment, Request $request)
    {
        $reason = $request->input('reason');
        $module = $assignment->module;

        $result = WorkforceService::unassign($assignment, $reason);

        if ($result['error']) {
            return back()->withErrors(['unassign' => $result['error']]);
        }

        return redirect()
            ->route('hr.workforce.module', $module)
            ->with('success', "Assignment ended successfully.");
    }

    /**
     * Suspend an assignment.
     */
    public function suspend(WorkforceAssignment $assignment, Request $request)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);

        $result = WorkforceService::suspend($assignment, $data['reason']);

        if ($result['error']) {
            return back()->withErrors(['suspend' => $result['error']]);
        }

        return back()->with('success', 'Assignment suspended.');
    }

    /**
     * Reactivate a suspended assignment.
     */
    public function reactivate(WorkforceAssignment $assignment)
    {
        $result = WorkforceService::reactivate($assignment);

        if ($result['error']) {
            return back()->withErrors(['reactivate' => $result['error']]);
        }

        return back()->with('success', 'Assignment reactivated.');
    }

    /**
     * Employee's full assignment history.
     */
    public function employee(HrEmployee $employee)
    {
        $assignments = WorkforceAssignment::with('module')
            ->where('employee_id', $employee->id)
            ->orderByDesc('assigned_at')
            ->get();

        return view('hr.workforce.employee', compact('employee', 'assignments'));
    }
}
