<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrEmployee;
use App\Models\HR\WorkforceAssignment;
use App\Models\Module;
use App\Models\ModuleDepartment;
use App\Models\ModulePosition;
use App\Models\ModuleRole;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrModuleWorkforceController extends Controller
{
    // ── Shared: resolve module by slug ────────────────────────────────────────
    private function mod(string $slug): Module
    {
        return Module::where('slug', $slug)->where('is_active', true)->firstOrFail();
    }

    // ── Overview ──────────────────────────────────────────────────────────────
    public function overview(string $slug)
    {
        $module = $this->mod($slug);
        [$stats, $recentAssignments, $expiringAssignments] = $this->baseStats($module);

        return view('hr.workforce.module-hub', compact(
            'module', 'stats', 'recentAssignments', 'expiringAssignments',
        ) + ['tab' => 'overview']);
    }

    // ── Employees ─────────────────────────────────────────────────────────────
    public function employees(string $slug, Request $request)
    {
        $module = $this->mod($slug);

        $query = WorkforceAssignment::with([
                'employee.department', 'employee.position',
                'moduleDepartment', 'modulePosition', 'moduleRole', 'reportingManager',
            ])
            ->where('module_id', $module->id)
            ->where('status', 'active');

        if ($request->dept_id)   $query->where('module_department_id', $request->dept_id);
        if ($request->pos_id)    $query->where('module_position_id',   $request->pos_id);
        if ($request->type)      $query->where('assignment_type',      $request->type);
        if ($request->search)    $query->whereHas('employee', fn($q) =>
            $q->where('first_name', 'like', "%{$request->search}%")
              ->orWhere('last_name',  'like', "%{$request->search}%")
              ->orWhere('employee_no','like', "%{$request->search}%"));

        $assignments  = $query->orderByRaw("FIELD(assignment_type,'primary','secondary','temporary','acting','project_based')")
                              ->paginate(20)->withQueryString();

        $departments  = ModuleDepartment::where('module_id', $module->id)->where('status','active')->orderBy('name')->get();
        $positions    = ModulePosition::where('module_id',   $module->id)->where('status','active')->orderBy('name')->get();
        [$stats]      = $this->baseStats($module);

        return view('hr.workforce.module-hub', compact(
            'module', 'stats', 'assignments', 'departments', 'positions',
        ) + ['tab' => 'employees']);
    }

    // ── Departments ───────────────────────────────────────────────────────────
    public function departments(string $slug, Request $request)
    {
        $module = $this->mod($slug);

        $departments = ModuleDepartment::where('module_id', $module->id)
            ->withCount(['workforceAssignments as employee_count' =>
                fn($q) => $q->where('status','active')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(25)->withQueryString();

        [$stats] = $this->baseStats($module);

        return view('hr.workforce.module-hub', compact('module', 'stats', 'departments')
            + ['tab' => 'departments']);
    }

    // ── Positions ─────────────────────────────────────────────────────────────
    public function positions(string $slug, Request $request)
    {
        $module = $this->mod($slug);

        $positions = ModulePosition::where('module_id', $module->id)
            ->withCount(['workforceAssignments as employee_count' =>
                fn($q) => $q->where('status','active')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(25)->withQueryString();

        [$stats] = $this->baseStats($module);

        return view('hr.workforce.module-hub', compact('module', 'stats', 'positions')
            + ['tab' => 'positions']);
    }

    // ── Roles ─────────────────────────────────────────────────────────────────
    public function roles(string $slug, Request $request)
    {
        $module = $this->mod($slug);

        $roles = ModuleRole::where('module_id', $module->id)
            ->with('permissions')
            ->withCount(['workforceAssignments as employee_count' =>
                fn($q) => $q->where('status','active')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(25)->withQueryString();

        [$stats] = $this->baseStats($module);

        return view('hr.workforce.module-hub', compact('module', 'stats', 'roles')
            + ['tab' => 'roles']);
    }

    // ── Assignments ───────────────────────────────────────────────────────────
    public function assignments(string $slug, Request $request)
    {
        $module = $this->mod($slug);

        $query = WorkforceAssignment::with([
                'employee', 'moduleDepartment', 'modulePosition',
                'moduleRole', 'reportingManager', 'assignedBy',
            ])
            ->where('module_id', $module->id);

        if ($request->status)    $query->where('status',          $request->status);
        if ($request->type)      $query->where('assignment_type', $request->type);
        if ($request->access)    $query->where('access_level',    $request->access);

        $assignments = $query
            ->orderByRaw("FIELD(status,'active','suspended','ended')")
            ->orderByDesc('assigned_at')
            ->paginate(25)->withQueryString();

        [$stats] = $this->baseStats($module);

        return view('hr.workforce.module-hub', compact('module', 'stats', 'assignments')
            + ['tab' => 'assignments']);
    }

    // ── Org Chart ─────────────────────────────────────────────────────────────
    public function orgChart(string $slug, Request $request)
    {
        $module      = $this->mod($slug);
        $departments = ModuleDepartment::where('module_id', $module->id)->where('status','active')->orderBy('name')->get();
        $positions   = ModulePosition::where('module_id',   $module->id)->where('status','active')->orderBy('name')->get();

        $treeData = $this->buildTree(
            $module->id,
            $request->dept_id,
            $request->pos_id,
        );

        [$stats] = $this->baseStats($module);

        return view('hr.workforce.module-hub', compact(
            'module', 'stats', 'departments', 'positions', 'treeData',
        ) + ['tab' => 'org-chart']);
    }

    // ── Reports ───────────────────────────────────────────────────────────────
    public function reports(string $slug)
    {
        $module = $this->mod($slug);

        // Summary numbers
        $totalActive    = WorkforceAssignment::where('module_id', $module->id)->where('status','active')->count();
        $totalEnded     = WorkforceAssignment::where('module_id', $module->id)->where('status','ended')->count();
        $totalSuspended = WorkforceAssignment::where('module_id', $module->id)->where('status','suspended')->count();
        $totalAll       = WorkforceAssignment::where('module_id', $module->id)->count();

        // By type
        $byType = WorkforceAssignment::where('module_id', $module->id)
            ->where('status','active')
            ->select('assignment_type', DB::raw('COUNT(*) as n'))
            ->groupBy('assignment_type')
            ->pluck('n','assignment_type')->toArray();

        // By access level
        $byAccess = WorkforceAssignment::where('module_id', $module->id)
            ->where('status','active')
            ->select('access_level', DB::raw('COUNT(*) as n'))
            ->groupBy('access_level')
            ->pluck('n','access_level')->toArray();

        // By department
        $byDept = WorkforceAssignment::where('workforce_assignments.module_id', $module->id)
            ->where('workforce_assignments.status','active')
            ->join('module_departments','module_departments.id','=','workforce_assignments.module_department_id')
            ->select('module_departments.name', DB::raw('COUNT(*) as n'))
            ->groupBy('module_departments.name')
            ->orderByDesc('n')
            ->get();

        // By position
        $byPos = WorkforceAssignment::where('workforce_assignments.module_id', $module->id)
            ->where('workforce_assignments.status','active')
            ->join('module_positions','module_positions.id','=','workforce_assignments.module_position_id')
            ->select('module_positions.name', DB::raw('COUNT(*) as n'))
            ->groupBy('module_positions.name')
            ->orderByDesc('n')
            ->get();

        // By role
        $byRole = WorkforceAssignment::where('workforce_assignments.module_id', $module->id)
            ->where('workforce_assignments.status','active')
            ->join('module_roles','module_roles.id','=','workforce_assignments.module_role_id')
            ->select('module_roles.name', DB::raw('COUNT(*) as n'))
            ->groupBy('module_roles.name')
            ->orderByDesc('n')
            ->get();

        // Monthly assignments (last 6 months)
        $monthly = WorkforceAssignment::where('module_id', $module->id)
            ->where('assigned_at', '>=', now()->subMonths(6)->startOfMonth())
            ->select(
                DB::raw("DATE_FORMAT(assigned_at, '%Y-%m') as month"),
                DB::raw('COUNT(*) as n')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('n','month')->toArray();

        // Expiring
        $expiring = WorkforceAssignment::with('employee')
            ->where('module_id', $module->id)
            ->where('status','active')
            ->whereNotNull('planned_end_date')
            ->orderBy('planned_end_date')
            ->get();

        [$stats] = $this->baseStats($module);

        return view('hr.workforce.module-hub', compact(
            'module', 'stats',
            'totalActive', 'totalEnded', 'totalSuspended', 'totalAll',
            'byType', 'byAccess', 'byDept', 'byPos', 'byRole',
            'monthly', 'expiring',
        ) + ['tab' => 'reports']);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function baseStats(Module $module): array
    {
        $stats = [
            'active'      => WorkforceAssignment::where('module_id',$module->id)->where('status','active')->count(),
            'primary'     => WorkforceAssignment::where('module_id',$module->id)->where('status','active')->where('assignment_type','primary')->count(),
            'departments' => ModuleDepartment::where('module_id',$module->id)->where('status','active')->count(),
            'positions'   => ModulePosition::where('module_id',$module->id)->where('status','active')->count(),
            'roles'       => ModuleRole::where('module_id',$module->id)->where('status','active')->count(),
            'expiring'    => WorkforceAssignment::where('module_id',$module->id)->where('status','active')
                                ->whereNotNull('planned_end_date')
                                ->where('planned_end_date','<=',now()->addDays(14))->count(),
        ];

        $recentAssignments = WorkforceAssignment::with(['employee','moduleDepartment','modulePosition'])
            ->where('module_id',$module->id)->where('status','active')
            ->orderByDesc('assigned_at')->limit(5)->get();

        $expiringAssignments = WorkforceAssignment::with('employee')
            ->where('module_id',$module->id)->where('status','active')
            ->whereNotNull('planned_end_date')
            ->where('planned_end_date','<=',now()->addDays(30))
            ->orderBy('planned_end_date')->limit(5)->get();

        return [$stats, $recentAssignments, $expiringAssignments];
    }

    private function buildTree(?int $moduleId, ?int $deptId, ?int $posId): array
    {
        $query = WorkforceAssignment::with([
            'employee:id,first_name,last_name,photo',
            'moduleDepartment:id,name',
            'modulePosition:id,name',
            'moduleRole:id,name',
        ])->where('status','active');

        if ($moduleId) $query->where('module_id', $moduleId);
        if ($deptId)   $query->where('module_department_id', $deptId);
        if ($posId)    $query->where('module_position_id',   $posId);

        $assignments = $query->get();
        $nodes = [];

        foreach ($assignments as $a) {
            $empId = $a->employee_id;
            if (!isset($nodes[$empId]) || $a->assignment_type === 'primary') {
                $nodes[$empId] = [
                    'id'              => $empId,
                    'name'            => $a->employee?->full_name ?? 'Unknown',
                    'initials'        => $a->employee?->initials ?? '?',
                    'department'      => $a->moduleDepartment?->name,
                    'position'        => $a->modulePosition?->name,
                    'role'            => $a->moduleRole?->name,
                    'role_label'      => $a->role_in_module,
                    'assignment_type' => $a->assignment_type,
                    'type_label'      => $a->assignment_type_label,
                    'access_level'    => $a->access_level_label,
                    'module_color'    => '#1B1444',
                    'parent_id'       => $a->reporting_manager_id,
                    'employee_url'    => route('hr.employees.show', $empId),
                    'assigned_at'     => $a->assigned_at?->format('d M Y'),
                    'children'        => [],
                ];
            }
        }

        $roots = [];
        foreach ($nodes as $empId => &$node) {
            $pid = $node['parent_id'];
            if ($pid && isset($nodes[$pid])) {
                $nodes[$pid]['children'][] = &$node;
            } else {
                $roots[] = &$node;
            }
        }
        unset($node);
        return $roots;
    }
}
