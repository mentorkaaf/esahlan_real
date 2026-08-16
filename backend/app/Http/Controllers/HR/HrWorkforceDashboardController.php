<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\WorkforceAssignment;
use App\Models\Module;
use App\Models\ModuleDepartment;
use App\Models\ModulePosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrWorkforceDashboardController extends Controller
{
    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        // ── Top-level metrics ────────────────────────────────────────────────
        $totalActive  = WorkforceAssignment::where('status', 'active')->count();
        $totalPrimary = WorkforceAssignment::where('status', 'active')
                            ->where('assignment_type', 'primary')->count();
        $totalModules = WorkforceAssignment::where('status', 'active')
                            ->distinct('module_id')->count('module_id');
        $newThisWeek  = WorkforceAssignment::where('status', 'active')
                            ->where('assigned_at', '>=', now()->subDays(7))->count();

        // ── Module distribution ──────────────────────────────────────────────
        $moduleDistribution = WorkforceAssignment::query()
            ->join('modules', 'modules.id', '=', 'workforce_assignments.module_id')
            ->where('workforce_assignments.status', 'active')
            ->select('modules.id', 'modules.name', 'modules.color', 'modules.icon',
                     DB::raw('COUNT(*) as total'),
                     DB::raw("SUM(CASE WHEN assignment_type = 'primary' THEN 1 ELSE 0 END) as primary_count"),
                     DB::raw("SUM(CASE WHEN assignment_type != 'primary' THEN 1 ELSE 0 END) as secondary_count"))
            ->groupBy('modules.id', 'modules.name', 'modules.color', 'modules.icon')
            ->orderByDesc('total')
            ->get();

        // ── Assignment type breakdown ────────────────────────────────────────
        $typeBreakdown = WorkforceAssignment::where('status', 'active')
            ->select('assignment_type', DB::raw('COUNT(*) as total'))
            ->groupBy('assignment_type')
            ->pluck('total', 'assignment_type')
            ->toArray();

        // ── Department distribution (module departments) ─────────────────────
        $deptDistribution = WorkforceAssignment::query()
            ->join('module_departments', 'module_departments.id', '=', 'workforce_assignments.module_department_id')
            ->where('workforce_assignments.status', 'active')
            ->whereNotNull('workforce_assignments.module_department_id')
            ->select('module_departments.name',
                     DB::raw('COUNT(*) as total'))
            ->groupBy('module_departments.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // ── Position distribution ────────────────────────────────────────────
        $posDistribution = WorkforceAssignment::query()
            ->join('module_positions', 'module_positions.id', '=', 'workforce_assignments.module_position_id')
            ->where('workforce_assignments.status', 'active')
            ->whereNotNull('workforce_assignments.module_position_id')
            ->select('module_positions.name',
                     DB::raw('COUNT(*) as total'))
            ->groupBy('module_positions.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // ── Active assignments (recent 20) ───────────────────────────────────
        $activeAssignments = WorkforceAssignment::with([
                'employee.department', 'module', 'moduleDepartment', 'modulePosition', 'moduleRole',
            ])
            ->where('status', 'active')
            ->orderByDesc('assigned_at')
            ->limit(20)
            ->get();

        // ── Temporary / expiring assignments ────────────────────────────────
        $temporaryAssignments = WorkforceAssignment::with(['employee', 'module'])
            ->where('status', 'active')
            ->whereIn('assignment_type', ['temporary', 'acting', 'project_based'])
            ->orderBy('planned_end_date')
            ->get();

        $expiredCount = $temporaryAssignments->filter(fn($a) => $a->isExpired())->count();
        $expiringSoon = $temporaryAssignments->filter(
            fn($a) => !$a->isExpired()
                   && $a->planned_end_date
                   && $a->planned_end_date->lte(now()->addDays(14))
        )->count();

        // ── Access level breakdown ───────────────────────────────────────────
        $accessBreakdown = WorkforceAssignment::where('status', 'active')
            ->select('access_level', DB::raw('COUNT(*) as total'))
            ->groupBy('access_level')
            ->pluck('total', 'access_level')
            ->toArray();

        return view('hr.workforce.dashboard', compact(
            'totalActive', 'totalPrimary', 'totalModules', 'newThisWeek',
            'moduleDistribution', 'typeBreakdown', 'deptDistribution', 'posDistribution',
            'activeAssignments', 'temporaryAssignments', 'expiredCount', 'expiringSoon',
            'accessBreakdown',
        ));
    }

    // ── Org Chart ─────────────────────────────────────────────────────────────

    public function orgChart(Request $request)
    {
        $modules     = Module::where('is_active', true)->orderBy('sort_order')->get();
        $departments = collect();
        $positions   = collect();

        $selectedModuleId = $request->module_id;
        $selectedDeptId   = $request->dept_id;
        $selectedPosId    = $request->pos_id;

        if ($selectedModuleId) {
            $departments = ModuleDepartment::where('module_id', $selectedModuleId)
                ->where('status', 'active')->orderBy('name')->get();
            $positions   = ModulePosition::where('module_id', $selectedModuleId)
                ->where('status', 'active')->orderBy('name')->get();
        }

        $treeData = $this->buildOrgTree($selectedModuleId, $selectedDeptId, $selectedPosId);

        return view('hr.workforce.org-chart', compact(
            'modules', 'departments', 'positions', 'treeData',
            'selectedModuleId', 'selectedDeptId', 'selectedPosId',
        ));
    }

    public function orgChartData(Request $request)
    {
        $treeData = $this->buildOrgTree(
            $request->module_id,
            $request->dept_id,
            $request->pos_id,
        );

        $departments = collect();
        $positions   = collect();

        if ($request->module_id) {
            $departments = ModuleDepartment::where('module_id', $request->module_id)
                ->where('status', 'active')->orderBy('name')
                ->get(['id', 'name']);
            $positions   = ModulePosition::where('module_id', $request->module_id)
                ->where('status', 'active')->orderBy('name')
                ->get(['id', 'name']);
        }

        return response()->json([
            'tree'        => $treeData,
            'departments' => $departments,
            'positions'   => $positions,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildOrgTree(?int $moduleId, ?int $deptId, ?int $posId): array
    {
        $query = WorkforceAssignment::with([
            'employee:id,first_name,last_name,photo',
            'module:id,name,color,icon',
            'moduleDepartment:id,name',
            'modulePosition:id,name',
            'moduleRole:id,name',
        ])
        ->where('status', 'active');

        if ($moduleId) {
            $query->where('module_id', $moduleId);
        }
        if ($deptId) {
            $query->where('module_department_id', $deptId);
        }
        if ($posId) {
            $query->where('module_position_id', $posId);
        }

        $assignments = $query->get();

        // Build flat node map: keyed by employee_id
        // (one node per employee per module — primary assignment wins if multiple)
        $nodes = [];
        foreach ($assignments as $a) {
            $empId = $a->employee_id;
            // Primary wins over secondary for node representation
            if (!isset($nodes[$empId]) || $a->assignment_type === 'primary') {
                $nodes[$empId] = [
                    'id'               => $empId,
                    'assignment_id'    => $a->id,
                    'name'             => $a->employee?->full_name ?? 'Unknown',
                    'initials'         => $a->employee?->initials ?? '?',
                    'photo'            => $a->employee?->photo,
                    'module_id'        => $a->module_id,
                    'module_name'      => $a->module?->name,
                    'module_color'     => $a->module?->color ?? '#1B1444',
                    'module_icon'      => $a->module?->icon ?? 'fas fa-cube',
                    'department'       => $a->moduleDepartment?->name,
                    'position'         => $a->modulePosition?->name,
                    'role'             => $a->moduleRole?->name,
                    'role_label'       => $a->role_in_module,
                    'assignment_type'  => $a->assignment_type,
                    'type_label'       => $a->assignment_type_label,
                    'access_level'     => $a->access_level_label,
                    'parent_id'        => $a->reporting_manager_id, // employee_id of manager
                    'employee_url'     => route('hr.employees.show', $a->employee_id),
                    'assigned_at'      => $a->assigned_at?->format('d M Y'),
                    'children'         => [],
                ];
            }
        }

        // Recursive tree builder
        $roots = [];
        foreach ($nodes as $empId => &$node) {
            $parentId = $node['parent_id'];
            if ($parentId && isset($nodes[$parentId])) {
                $nodes[$parentId]['children'][] = &$node;
            } else {
                $roots[] = &$node;
            }
        }
        unset($node);

        // Sort roots: primary first
        usort($roots, fn($a, $b) => ($a['assignment_type'] === 'primary' ? 0 : 1)
                                  - ($b['assignment_type'] === 'primary' ? 0 : 1));

        return $roots;
    }
}
