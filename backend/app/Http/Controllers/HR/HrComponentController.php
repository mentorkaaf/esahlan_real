<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrSalaryComponent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HrComponentController extends Controller
{
    public function index()
    {
        $components = HrSalaryComponent::orderBy('type')->orderBy('name')->get();

        return view('hr.components.index', compact('components'));
    }

    public function create()
    {
        return view('hr.components.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        HrSalaryComponent::create($data);

        return redirect()->route('hr.components.index')
            ->with('success', "Component '{$data['name']}' created.");
    }

    public function edit(HrSalaryComponent $component)
    {
        return view('hr.components.edit', compact('component'));
    }

    public function update(Request $request, HrSalaryComponent $component)
    {
        $data = $this->validated($request, $component->id);

        $component->update($data);

        return redirect()->route('hr.components.index')
            ->with('success', 'Component updated.');
    }

    public function destroy(HrSalaryComponent $component)
    {
        $component->delete();

        return redirect()->route('hr.components.index')
            ->with('success', 'Component deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'        => 'required|string|max:100',
            'code'        => 'required|string|max:30|unique:hr_salary_components,code,' . $ignoreId,
            'type'        => 'required|in:earning,deduction',
            'calculation' => 'required|in:fixed,percentage',
            'value'       => 'required|numeric|min:0',
            'is_taxable'  => 'boolean',
            'is_active'   => 'boolean',
            'description' => 'nullable|string|max:500',
        ]);
    }
}
