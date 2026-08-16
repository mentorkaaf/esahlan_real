<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrEmployee;
use App\Models\HR\HrDepartment;
use App\Models\HR\HrPosition;
use App\Services\HR\AuditService;
use App\Services\HR\WorkforcePermissionService;
use App\Services\HR\WorkforceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HrEmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = HrEmployee::with(['department', 'position'])
            ->when($request->search, fn($q, $s) =>
                $q->where(fn($q2) =>
                    $q2->where('first_name', 'like', "%$s%")
                       ->orWhere('last_name', 'like', "%$s%")
                       ->orWhere('employee_no', 'like', "%$s%")
                       ->orWhere('email', 'like', "%$s%")
                ))
            ->when($request->department_id, fn($q, $d) =>
                $q->where('department_id', $d))
            ->when($request->status, fn($q, $s) =>
                $q->where('status', $s))
            ->orderBy('first_name');

        $employees   = $query->paginate(20)->withQueryString();
        $departments = HrDepartment::where('is_active', true)->orderBy('name')->get();

        return view('hr.employees.index', compact('employees', 'departments'));
    }

    public function create()
    {
        $departments = HrDepartment::where('is_active', true)->orderBy('name')->get();
        $positions   = HrPosition::where('is_active', true)->orderBy('title')->get();

        return view('hr.employees.create', compact('departments', 'positions'));
    }

    public function store(Request $request)
    {
        $data = $this->validateEmployee($request);

        // Photo upload
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('hr/photos', 'public');
        }

        $data['employee_no']    = HrEmployee::generateEmployeeNo();
        $data['emergency_contact'] = $request->emergency_contact ? json_decode($request->emergency_contact, true) : null;

        $employee = HrEmployee::create($data);

        return redirect()->route('hr.employees.show', $employee)
            ->with('success', "Employee {$employee->full_name} created successfully.");
    }

    public function show(HrEmployee $employee)
    {
        $employee->load([
            'department', 'position', 'contracts', 'documents',
            // Business Suite workspace
            'workforceAssignments' => fn($q) => $q->with([
                'module', 'moduleDepartment', 'modulePosition',
                'moduleRole.permissions', 'reportingManager', 'assignedBy',
            ])->orderByRaw("FIELD(status, 'active', 'suspended', 'ended')")
              ->orderByRaw("FIELD(assignment_type, 'primary', 'secondary', 'temporary', 'acting', 'project_based')")
              ->orderBy('assigned_at'),
        ]);

        // Split active vs history
        $activeAssignments = $employee->workforceAssignments->where('status', 'active');
        $pastAssignments   = $employee->workforceAssignments->whereIn('status', ['ended', 'suspended']);

        $primaryAssignment   = $activeAssignments->firstWhere('assignment_type', 'primary');
        $secondaryAssignments = $activeAssignments->where('assignment_type', '!=', 'primary');

        // Aggregate all permissions from active assignments (deduplicated by slug)
        $allPermissions = $activeAssignments
            ->flatMap(fn($a) => $a->moduleRole?->permissions ?? collect())
            ->unique('id')
            ->groupBy('group');

        // Active workspace (for "My Workspaces" switcher)
        $employee->loadMissing('activeWorkspace.module');
        $resolvedWorkspace = $employee->active_workspace_id
            ? $employee->activeWorkspace
            : $activeAssignments->firstWhere('assignment_type', 'primary') ?? $activeAssignments->first();

        return view('hr.employees.show', compact(
            'employee',
            'activeAssignments', 'pastAssignments',
            'primaryAssignment', 'secondaryAssignments',
            'allPermissions',
            'resolvedWorkspace',
        ));
    }

    public function edit(HrEmployee $employee)
    {
        $departments = HrDepartment::where('is_active', true)->orderBy('name')->get();
        $positions   = HrPosition::where('is_active', true)->orderBy('title')->get();

        return view('hr.employees.edit', compact('employee', 'departments', 'positions'));
    }

    public function update(Request $request, HrEmployee $employee)
    {
        $data = $this->validateEmployee($request, $employee->id);

        if ($request->hasFile('photo')) {
            if ($employee->photo) Storage::disk('public')->delete($employee->photo);
            $data['photo'] = $request->file('photo')->store('hr/photos', 'public');
        }

        if ($request->emergency_contact) {
            $data['emergency_contact'] = json_decode($request->emergency_contact, true);
        }

        $oldStatus = $employee->status;
        $employee->update($data);

        // When employee is suspended or resigned → suspend all active assignments + revoke access
        $newStatus = $employee->fresh()->status;
        if ($oldStatus !== $newStatus && in_array($newStatus, ['suspended', 'resigned', 'terminated'])) {
            $activeAssignments = $employee->workforceAssignments()->where('status', 'active')->get();
            foreach ($activeAssignments as $assignment) {
                WorkforceService::suspend($assignment, "Employee status changed to {$newStatus}");
            }
            WorkforcePermissionService::revokeAllEmployeeAccess($employee);
            AuditService::logWorkforceEmployee('workforce.all_access_revoked', $employee, null, [
                'reason'   => "employee_status_changed_to_{$newStatus}",
                'old_status' => $oldStatus,
            ]);
        }

        // When employee is reactivated → log it
        if ($oldStatus !== $newStatus && $newStatus === 'active') {
            AuditService::log('employee.reactivated', $employee,
                ['status' => $oldStatus], ['status' => 'active']);
        }

        return redirect()->route('hr.employees.show', $employee)
            ->with('success', 'Employee record updated.');
    }

    public function destroy(HrEmployee $employee)
    {
        $this->authorize_manager();

        $employee->delete();

        return redirect()->route('hr.employees.index')
            ->with('success', "Employee {$employee->full_name} archived.");
    }

    public function terminate(Request $request, HrEmployee $employee)
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $before = $employee->only('status');
        $employee->update(['status' => 'terminated']);

        // End all active workforce assignments + revoke all module access
        $activeAssignments = $employee->workforceAssignments()
            ->where('status', 'active')->get();

        foreach ($activeAssignments as $assignment) {
            WorkforceService::unassign($assignment, 'Employee terminated: ' . $request->reason);
        }

        $revokedCount = WorkforcePermissionService::revokeAllEmployeeAccess($employee);

        AuditService::log('employee.terminated', $employee, $before, [
            'status'              => 'terminated',
            'reason'              => $request->reason,
            'assignments_ended'   => $activeAssignments->count(),
            'modules_revoked'     => $revokedCount,
        ]);

        AuditService::logWorkforceEmployee('workforce.all_access_revoked', $employee, null, [
            'reason'          => 'employee_terminated',
            'modules_revoked' => $revokedCount,
        ]);

        return redirect()->route('hr.employees.show', $employee)
            ->with('success', 'Employee has been terminated. All module access revoked.');
    }

    // ──────────────────────────────────────────────────────────────
    private function validateEmployee(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'first_name'        => 'required|string|max:100',
            'middle_name'       => 'nullable|string|max:100',
            'last_name'         => 'required|string|max:100',
            'gender'            => 'required|in:male,female,other',
            'dob'               => 'nullable|date',
            'national_id'       => 'nullable|string|max:50',
            'phone'             => 'required|string|max:20',
            'email'             => 'nullable|email|max:150',
            'district'          => 'nullable|string|max:100',
            'address'           => 'nullable|string|max:255',
            'department_id'     => 'required|exists:hr_departments,id',
            'position_id'       => 'required|exists:hr_positions,id',
            'employment_type'   => 'required|in:full_time,part_time,contract,intern',
            'status'            => 'required|in:active,probation,suspended,terminated,resigned',
            'hire_date'         => 'required|date',
            'probation_end'     => 'nullable|date',
            'base_salary'       => 'nullable|numeric|min:0',
            'bank_account'      => 'nullable|string|max:50',
            'mobile_money_number' => 'nullable|string|max:20',
            'photo'             => 'nullable|image|max:2048',
        ]);
    }

    private function authorize_manager(): void
    {
        if (!Auth::guard('hr')->user()->isManager()) {
            abort(403);
        }
    }
}
