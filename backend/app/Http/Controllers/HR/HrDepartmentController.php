<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrDepartment;
use App\Models\HR\HrEmployee;
use Illuminate\Http\Request;

class HrDepartmentController extends Controller
{
    public function index()
    {
        $departments = HrDepartment::withCount('employees')
            ->with('parent', 'manager')
            ->orderBy('name')
            ->get();

        return view('hr.departments.index', compact('departments'));
    }

    public function create()
    {
        $parents   = HrDepartment::where('is_active', true)->orderBy('name')->get();
        $employees = HrEmployee::where('status', 'active')->orderBy('first_name')->get();

        return view('hr.departments.create', compact('parents', 'employees'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                => 'required|string|max:150',
            'code'                => 'required|string|max:20|unique:hr_departments,code',
            'parent_id'           => 'nullable|exists:hr_departments,id',
            'manager_employee_id' => 'nullable|exists:hr_employees,id',
            'description'         => 'nullable|string|max:1000',
            'is_active'           => 'boolean',
        ]);

        $dept = HrDepartment::create($data);

        return redirect()->route('hr.departments.index')
            ->with('success', "Department '{$dept->name}' created.");
    }

    public function show(HrDepartment $department)
    {
        $department->load(['parent', 'children', 'manager', 'employees.position']);

        return view('hr.departments.show', compact('department'));
    }

    public function edit(HrDepartment $department)
    {
        $parents   = HrDepartment::where('is_active', true)
            ->where('id', '!=', $department->id)
            ->orderBy('name')->get();
        $employees = HrEmployee::where('status', 'active')->orderBy('first_name')->get();

        return view('hr.departments.edit', compact('department', 'parents', 'employees'));
    }

    public function update(Request $request, HrDepartment $department)
    {
        $data = $request->validate([
            'name'                => 'required|string|max:150',
            'code'                => 'required|string|max:20|unique:hr_departments,code,' . $department->id,
            'parent_id'           => 'nullable|exists:hr_departments,id',
            'manager_employee_id' => 'nullable|exists:hr_employees,id',
            'description'         => 'nullable|string|max:1000',
            'is_active'           => 'boolean',
        ]);

        $department->update($data);

        return redirect()->route('hr.departments.index')
            ->with('success', 'Department updated.');
    }

    public function destroy(HrDepartment $department)
    {
        if ($department->employees()->count() > 0) {
            return back()->with('error', 'Cannot delete a department that has employees. Reassign them first.');
        }

        $department->delete();

        return redirect()->route('hr.departments.index')
            ->with('success', 'Department deleted.');
    }
}
