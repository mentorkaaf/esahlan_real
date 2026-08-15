<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrEmployee;
use App\Models\HR\HrContract;
use Illuminate\Http\Request;

class HrContractController extends Controller
{
    public function create(HrEmployee $employee)
    {
        return view('hr.contracts.create', compact('employee'));
    }

    public function store(Request $request, HrEmployee $employee)
    {
        $data = $request->validate([
            'type'       => 'required|string|max:50',
            'start_date' => 'required|date',
            'end_date'   => 'nullable|date|after:start_date',
            'salary'     => 'required|numeric|min:0',
            'notes'      => 'nullable|string|max:2000',
            'file'       => 'nullable|file|mimes:pdf,doc,docx|max:5120',
        ]);

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('hr/contracts', 'public');
        }

        $data['employee_id'] = $employee->id;
        $data['status']      = 'active';

        HrContract::create($data);

        return redirect()->route('hr.employees.show', $employee)
            ->with('success', 'Contract added.');
    }

    public function show(HrContract $contract)
    {
        return view('hr.contracts.show', compact('contract'));
    }

    public function edit(HrContract $contract)
    {
        return view('hr.contracts.edit', compact('contract'));
    }

    public function update(Request $request, HrContract $contract)
    {
        $data = $request->validate([
            'type'       => 'required|string|max:50',
            'start_date' => 'required|date',
            'end_date'   => 'nullable|date',
            'salary'     => 'required|numeric|min:0',
            'status'     => 'required|in:active,expired,terminated',
            'notes'      => 'nullable|string|max:2000',
        ]);

        $contract->update($data);

        return redirect()->route('hr.employees.show', $contract->employee)
            ->with('success', 'Contract updated.');
    }

    public function destroy(HrContract $contract)
    {
        $employee = $contract->employee;
        $contract->delete();

        return redirect()->route('hr.employees.show', $employee)
            ->with('success', 'Contract removed.');
    }
}
