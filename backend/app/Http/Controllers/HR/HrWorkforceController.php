<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrEmployee;
use App\Models\HR\WorkforceAssignment;
use App\Models\Module;
use App\Models\ModuleRole;
use App\Services\HR\WorkforceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
     * Show the 10-step assignment wizard.
     */
    public function wizard(Request $request)
    {
        $employees = HrEmployee::whereIn('status', ['active', 'probation'])
            ->with('department', 'position')
            ->orderBy('first_name')
            ->get();

        $modules = Module::where('is_active', true)->orderBy('sort_order')->get();

        $selectedEmployee = $request->employee_id
            ? HrEmployee::with('department', 'position')->find($request->employee_id)
            : null;

        $selectedModule = $request->module_id
            ? Module::find($request->module_id)
            : null;

        $assignmentTypes = WorkforceAssignment::assignmentTypes();
        $accessLevels    = WorkforceAssignment::accessLevels();

        // Employees eligible to be reporting managers (active employees)
        $managers = HrEmployee::whereIn('status', ['active'])
            ->orderBy('first_name')
            ->get();

        return view('hr.workforce.wizard', compact(
            'employees', 'modules', 'selectedEmployee', 'selectedModule',
            'assignmentTypes', 'accessLevels', 'managers'
        ));
    }

    /**
     * Show assign form (legacy — kept for backwards compat).
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
            'module_position_id'   => ['nullable', 'exists:module_positions,id'],
            'module_role_id'       => ['nullable', 'exists:module_roles,id'],
            'role_in_module'       => ['nullable', 'string', 'max:60'],
            'assignment_type'      => ['nullable', 'in:primary,secondary,temporary,acting,project_based'],
            'access_level'         => ['nullable', 'in:read_only,standard,elevated,admin'],
            'start_date'           => ['nullable', 'date'],
            'planned_end_date'     => ['nullable', 'date', 'after_or_equal:start_date'],
            'reporting_manager_id' => ['nullable', 'exists:hr_employees,id', 'different:employee_id'],
            'notes'                => ['nullable', 'string', 'max:1000'],
        ]);

        $employee = HrEmployee::findOrFail($data['employee_id']);
        $module   = Module::findOrFail($data['module_id']);

        $result = WorkforceService::assign(
            employee:            $employee,
            module:              $module,
            roleInModule:        $data['role_in_module'] ?? 'staff',
            notes:               $data['notes'] ?? null,
            moduleDepartmentId:  !empty($data['module_department_id']) ? (int) $data['module_department_id'] : null,
            modulePositionId:    !empty($data['module_position_id'])   ? (int) $data['module_position_id']   : null,
            moduleRoleId:        !empty($data['module_role_id'])        ? (int) $data['module_role_id']        : null,
            assignmentType:      $data['assignment_type']      ?? 'primary',
            accessLevel:         $data['access_level']          ?? 'standard',
            startDate:           $data['start_date']            ?? null,
            plannedEndDate:      $data['planned_end_date']      ?? null,
            reportingManagerId:  !empty($data['reporting_manager_id']) ? (int) $data['reporting_manager_id'] : null,
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
