<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrEmployee;
use App\Models\HR\WorkforceAssignment;
use App\Services\HR\AuditService;
use Illuminate\Http\Request;

class HrWorkspaceController extends Controller
{
    /**
     * Switch the active workspace for an employee.
     *
     * POST /hr/employees/{employee}/workspace/switch
     */
    public function switch(Request $request, HrEmployee $employee)
    {
        $request->validate([
            'assignment_id' => ['required', 'integer', 'exists:workforce_assignments,id'],
        ]);

        $assignment = WorkforceAssignment::find($request->assignment_id);

        // Must belong to this employee and be active
        if ($assignment->employee_id !== $employee->id) {
            return back()->with('error', 'Invalid workspace selection.');
        }

        if ($assignment->status !== 'active') {
            return back()->with('error', 'Only active assignments can be set as the active workspace.');
        }

        $old = $employee->active_workspace_id;

        if (!$employee->switchWorkspace($assignment)) {
            return back()->with('error', 'Could not switch workspace.');
        }

        AuditService::log('workspace.switched', $employee, ['active_workspace_id' => $old], [
            'active_workspace_id' => $assignment->id,
            'module'              => $assignment->module?->slug,
        ]);

        $moduleName = $assignment->module?->name ?? 'Unknown';

        return back()->with('success', "Active workspace switched to {$moduleName}.");
    }

    /**
     * Auto-resolve and set the workspace if none is set yet (called on first visit).
     *
     * POST /hr/employees/{employee}/workspace/auto-resolve
     */
    public function autoResolve(HrEmployee $employee)
    {
        $workspace = $employee->resolvedWorkspace();

        if (!$workspace) {
            return response()->json(['workspace' => null]);
        }

        return response()->json([
            'workspace' => [
                'id'          => $workspace->id,
                'module_name' => $workspace->module?->name,
                'module_slug' => $workspace->module?->slug,
                'module_color'=> $workspace->module?->color,
                'type'        => $workspace->assignment_type_label,
                'role'        => $workspace->moduleRole?->name,
                'access'      => $workspace->access_level_label,
            ],
        ]);
    }
}
