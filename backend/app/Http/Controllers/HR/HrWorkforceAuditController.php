<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrAuditLog;
use App\Models\HR\HrEmployee;
use App\Models\Module;
use Illuminate\Http\Request;

class HrWorkforceAuditController extends Controller
{
    /**
     * Filterable workforce audit log.
     * Shows all 10+ workforce event types with before/after diffs.
     */
    public function index(Request $request)
    {
        $query = HrAuditLog::query()
            ->where('category', 'workforce')
            ->with(['employee', 'module'])
            ->latest('created_at');

        // Filters
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('module_id')) {
            $query->where('module_id', $request->module_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('actor_name')) {
            $query->where('actor_name', 'like', '%' . $request->actor_name . '%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('severity')) {
            $actions = match($request->severity) {
                'high'   => ['employee.terminated', 'workforce.all_access_revoked', 'workforce.permission_revoked', 'workforce.access_revoked', 'workforce.expired'],
                'medium' => ['workforce.assigned', 'workforce.unassigned', 'workforce.suspended', 'workforce.permission_granted', 'workforce.access_granted', 'workforce.role_changed'],
                'low'    => ['workforce.reactivated', 'workforce.position_changed', 'workforce.department_changed'],
                default  => [],
            };
            if ($actions) $query->whereIn('action', $actions);
        }

        $logs = $query->paginate(50)->withQueryString();

        // Sidebar filter data
        $employees = HrEmployee::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_id']);
        $modules   = Module::orderBy('sort_order')->get(['id', 'name', 'slug']);
        $actions   = HrAuditLog::where('category', 'workforce')
            ->distinct('action')->pluck('action')->sort()->values();

        // Stats for this filter set (unfiltered counts)
        $stats = [
            'total'     => HrAuditLog::where('category', 'workforce')->count(),
            'today'     => HrAuditLog::where('category', 'workforce')->whereDate('created_at', today())->count(),
            'this_week' => HrAuditLog::where('category', 'workforce')->whereBetween('created_at', [now()->startOfWeek(), now()])->count(),
            'high'      => HrAuditLog::where('category', 'workforce')->whereIn('action', [
                'employee.terminated', 'workforce.all_access_revoked',
                'workforce.permission_revoked', 'workforce.access_revoked', 'workforce.expired',
            ])->count(),
        ];

        return view('hr.audit.workforce', compact('logs', 'employees', 'modules', 'actions', 'stats'));
    }

    /**
     * Show a single audit log entry with full before/after diff.
     */
    public function show(HrAuditLog $log)
    {
        $log->load(['employee', 'module', 'actor']);
        return view('hr.audit.workforce-entry', compact('log'));
    }
}
