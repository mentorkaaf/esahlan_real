<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrPosition;
use App\Models\HR\WorkforceAssignment;
use App\Models\Module;
use App\Models\ModuleDepartment;
use App\Models\ModulePosition;
use App\Services\HR\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HrModulePositionController extends Controller
{
    /**
     * List positions with module / dept / status / level filters.
     */
    public function index(Request $request)
    {
        $modules    = Module::orderBy('sort_order')->get();
        $moduleId   = $request->integer('module_id') ?: null;
        $deptId     = $request->integer('dept_id') ?: null;
        $status     = $request->input('status', 'active');
        $level      = $request->input('level', '');

        $depts = $moduleId
            ? ModuleDepartment::where('module_id', $moduleId)->orderBy('sort_order')->get()
            : collect();

        $query = ModulePosition::with(['module', 'moduleDepartment', 'hrPosition'])
            ->withCount(['activeAssignments'])
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($moduleId) $query->where('module_id', $moduleId);
        if ($deptId)   $query->where('module_department_id', $deptId);
        if ($status !== 'all') $query->where('status', $status);
        if ($level)    $query->where('level', $level);

        $positions      = $query->get();
        $selectedModule = $moduleId ? Module::find($moduleId) : null;
        $selectedDept   = $deptId   ? ModuleDepartment::find($deptId) : null;

        return view('hr.module-positions.index', compact(
            'positions', 'modules', 'depts',
            'selectedModule', 'selectedDept',
            'status', 'level'
        ));
    }

    /**
     * Show create form.
     */
    public function create(Request $request)
    {
        $modules      = Module::where('is_active', true)->orderBy('sort_order')->get();
        $hrPositions  = HrPosition::where('is_active', true)->orderBy('title')->get();
        $levels       = ModulePosition::levels();

        $selectedModule = $request->module_id ? Module::find($request->module_id) : null;
        $depts = $selectedModule
            ? ModuleDepartment::where('module_id', $selectedModule->id)->where('status', 'active')->orderBy('sort_order')->get()
            : collect();

        return view('hr.module-positions.create', compact('modules', 'hrPositions', 'levels', 'selectedModule', 'depts'));
    }

    /**
     * Store a new module position.
     * Auto-links to hr_position if name matches an existing company position title.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'module_id'            => ['required', 'exists:modules,id'],
            'module_department_id' => ['nullable', 'exists:module_departments,id'],
            'hr_position_id'       => ['nullable', 'exists:hr_positions,id'],
            'name'                 => [
                'required', 'string', 'max:120',
                Rule::unique('module_positions')->where(fn($q) => $q->where('module_id', $request->module_id)),
            ],
            'description'          => ['nullable', 'string', 'max:500'],
            'level'                => ['required', Rule::in(ModulePosition::levels())],
            'sort_order'           => ['nullable', 'integer', 'min:0'],
        ]);

        // Auto-link: check if an HR position with the same title exists (case-insensitive)
        $hrPositionId = $data['hr_position_id'] ?? null;
        if (!$hrPositionId) {
            $match = HrPosition::whereRaw('LOWER(title) = ?', [strtolower($data['name'])])->first();
            $hrPositionId = $match?->id;
        }

        $position = ModulePosition::create([
            'module_id'            => $data['module_id'],
            'module_department_id' => $data['module_department_id'] ?? null,
            'hr_position_id'       => $hrPositionId,
            'name'                 => $data['name'],
            'description'          => $data['description'] ?? null,
            'level'                => $data['level'],
            'status'               => 'active',
            'sort_order'           => $data['sort_order'] ?? 0,
        ]);

        AuditService::log('module_position.created', $position, null, [
            'module'  => $position->module?->slug,
            'name'    => $position->name,
            'reused'  => $hrPositionId ? 'yes' : 'no',
        ]);

        return redirect()
            ->route('hr.module-positions.show', $position)
            ->with('success', "Position \"{$position->name}\" created." . ($hrPositionId ? ' (Linked to existing HR position.)' : ''));
    }

    /**
     * Position detail — employees holding this position.
     */
    public function show(ModulePosition $modulePosition)
    {
        $modulePosition->load(['module', 'moduleDepartment', 'hrPosition']);

        $assignments = WorkforceAssignment::with(['employee.department', 'employee.position', 'moduleDepartment'])
            ->where('module_position_id', $modulePosition->id)
            ->orderBy('status')
            ->orderBy('assigned_at')
            ->get();

        return view('hr.module-positions.show', compact('modulePosition', 'assignments'));
    }

    /**
     * Show edit form.
     */
    public function edit(ModulePosition $modulePosition)
    {
        $hrPositions = HrPosition::where('is_active', true)->orderBy('title')->get();
        $levels      = ModulePosition::levels();
        $depts       = ModuleDepartment::where('module_id', $modulePosition->module_id)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get();

        return view('hr.module-positions.edit', compact('modulePosition', 'hrPositions', 'levels', 'depts'));
    }

    /**
     * Update a position.
     */
    public function update(Request $request, ModulePosition $modulePosition)
    {
        $data = $request->validate([
            'module_department_id' => ['nullable', 'exists:module_departments,id'],
            'hr_position_id'       => ['nullable', 'exists:hr_positions,id'],
            'name'                 => [
                'required', 'string', 'max:120',
                Rule::unique('module_positions')
                    ->where(fn($q) => $q->where('module_id', $modulePosition->module_id))
                    ->ignore($modulePosition->id),
            ],
            'description'          => ['nullable', 'string', 'max:500'],
            'level'                => ['required', Rule::in(ModulePosition::levels())],
            'sort_order'           => ['nullable', 'integer', 'min:0'],
        ]);

        $before = $modulePosition->only('name', 'description', 'level', 'hr_position_id', 'module_department_id');

        // Auto-link on rename if name now matches an HR position
        $hrPositionId = $data['hr_position_id'] ?? null;
        if (!$hrPositionId) {
            $match = HrPosition::whereRaw('LOWER(title) = ?', [strtolower($data['name'])])->first();
            $hrPositionId = $match?->id;
        }

        $modulePosition->update([
            'module_department_id' => $data['module_department_id'] ?? null,
            'hr_position_id'       => $hrPositionId,
            'name'                 => $data['name'],
            'description'          => $data['description'] ?? null,
            'level'                => $data['level'],
            'sort_order'           => $data['sort_order'] ?? $modulePosition->sort_order,
        ]);

        AuditService::log('module_position.updated', $modulePosition, $before, $modulePosition->only('name', 'level'));

        return redirect()
            ->route('hr.module-positions.show', $modulePosition)
            ->with('success', 'Position updated.');
    }

    /**
     * Change position status: activate / deactivate / archive.
     */
    public function status(Request $request, ModulePosition $modulePosition)
    {
        $newStatus = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],
        ])['status'];

        if ($modulePosition->isArchived()) {
            return back()->withErrors(['status' => 'Archived positions cannot be changed.']);
        }

        $before = $modulePosition->only('status');
        $modulePosition->update(['status' => $newStatus]);
        AuditService::log('module_position.status_changed', $modulePosition, $before, ['status' => $newStatus]);

        $labels = ['active' => 'activated', 'inactive' => 'deactivated', 'archived' => 'archived'];
        return back()->with('success', "Position {$labels[$newStatus]}.");
    }

    /**
     * JSON: positions for a module (optionally filtered by dept).
     */
    public function byModule(Module $module, Request $request)
    {
        $query = ModulePosition::where('module_id', $module->id)->where('status', 'active');

        if ($deptId = $request->integer('dept_id')) {
            $query->where(fn($q) => $q->whereNull('module_department_id')->orWhere('module_department_id', $deptId));
        }

        return response()->json($query->orderBy('sort_order')->get(['id', 'name', 'level']));
    }
}
