<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrPosition;
use App\Models\HR\HrDepartment;
use Illuminate\Http\Request;

class HrPositionController extends Controller
{
    public function index()
    {
        $positions = HrPosition::with('department')
            ->withCount('employees')
            ->orderBy('title')
            ->get();

        return view('hr.positions.index', compact('positions'));
    }

    public function create()
    {
        $departments = HrDepartment::where('is_active', true)->orderBy('name')->get();

        return view('hr.positions.create', compact('departments'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'department_id' => 'required|exists:hr_departments,id',
            'title'         => 'required|string|max:150',
            'grade'         => 'nullable|string|max:20',
            'min_salary'    => 'nullable|numeric|min:0',
            'max_salary'    => 'nullable|numeric|min:0',
            'description'   => 'nullable|string|max:1000',
            'is_active'     => 'boolean',
        ]);

        HrPosition::create($data);

        return redirect()->route('hr.positions.index')
            ->with('success', "Position '{$data['title']}' created.");
    }

    public function show(HrPosition $position)
    {
        $position->load(['department', 'employees']);

        return view('hr.positions.show', compact('position'));
    }

    public function edit(HrPosition $position)
    {
        $departments = HrDepartment::where('is_active', true)->orderBy('name')->get();

        return view('hr.positions.edit', compact('position', 'departments'));
    }

    public function update(Request $request, HrPosition $position)
    {
        $data = $request->validate([
            'department_id' => 'required|exists:hr_departments,id',
            'title'         => 'required|string|max:150',
            'grade'         => 'nullable|string|max:20',
            'min_salary'    => 'nullable|numeric|min:0',
            'max_salary'    => 'nullable|numeric|min:0',
            'description'   => 'nullable|string|max:1000',
            'is_active'     => 'boolean',
        ]);

        $position->update($data);

        return redirect()->route('hr.positions.index')
            ->with('success', 'Position updated.');
    }

    public function destroy(HrPosition $position)
    {
        if ($position->employees()->count() > 0) {
            return back()->with('error', 'Cannot delete a position that has active employees.');
        }

        $position->delete();

        return redirect()->route('hr.positions.index')
            ->with('success', 'Position deleted.');
    }
}
