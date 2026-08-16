<?php

namespace App\Services\HR;

use App\Models\HR\HrEmployee;
use App\Models\HR\WorkforceAssignment;
use App\Models\Module;
use App\Services\FcmService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WorkforceService
{
    /**
     * Assign an employee to a module.
     *
     * Returns ['assignment' => WorkforceAssignment, 'error' => null] or ['assignment' => null, 'error' => string].
     */
    public static function assign(
        HrEmployee $employee,
        Module $module,
        string $roleInModule,
        ?string $notes = null,
        ?int $moduleDepartmentId = null,
    ): array {
        // Employee must be hirable
        if (!in_array($employee->status, ['active', 'probation'])) {
            return ['assignment' => null, 'error' => "Employee {$employee->full_name} is not active."];
        }

        // Module must be active
        if (!$module->is_active) {
            return ['assignment' => null, 'error' => "Module {$module->name} is currently inactive."];
        }

        // Guard: no duplicate active assignment for this module
        $existing = WorkforceAssignment::where('employee_id', $employee->id)
            ->where('module_id', $module->id)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return [
                'assignment' => null,
                'error'      => "{$employee->full_name} is already assigned to {$module->name}.",
            ];
        }

        return DB::transaction(function () use ($employee, $module, $roleInModule, $notes, $moduleDepartmentId) {
            $assignment = WorkforceAssignment::create([
                'employee_id'          => $employee->id,
                'module_id'            => $module->id,
                'module_department_id' => $moduleDepartmentId,
                'role_in_module'       => $roleInModule ?: null,
                'status'               => 'active',
                'assigned_at'          => now(),
                'assigned_by'          => Auth::guard('hr')->id(),
                'notes'                => $notes,
            ]);

            // Sync to user_modules so canManageModule() works
            if ($employee->user_id) {
                DB::table('user_modules')->insertOrIgnore([
                    'user_id'   => $employee->user_id,
                    'module_id' => $module->id,
                ]);

                // eRent agent: upgrade user role
                if ($module->slug === 'erent' && $roleInModule === 'agent') {
                    $agentRole = \App\Models\Role::where('slug', 'rent_agent')->first();
                    if ($agentRole) {
                        \App\Models\User::where('id', $employee->user_id)
                            ->update(['role_id' => $agentRole->id]);
                    }
                }
            }

            AuditService::log('workforce.assigned', $assignment, null, [
                'employee'       => $employee->full_name,
                'module'         => $module->slug,
                'role_in_module' => $roleInModule,
            ]);

            // FCM — notify employee of new assignment
            static::pushAssignment($employee, $module, 'assigned');

            return ['assignment' => $assignment, 'error' => null];
        });
    }

    /**
     * End an active workforce assignment.
     */
    public static function unassign(WorkforceAssignment $assignment, ?string $reason = null): array
    {
        if (!$assignment->isActive()) {
            return ['error' => 'Assignment is not active.'];
        }

        DB::transaction(function () use ($assignment, $reason) {
            $before = $assignment->only('status');

            $assignment->update([
                'status'   => 'ended',
                'ended_at' => now(),
                'notes'    => $reason ? ($assignment->notes ? $assignment->notes . "\n[Ended] " . $reason : "[Ended] " . $reason) : $assignment->notes,
            ]);

            // Remove from user_modules if no other active assignments use this module
            $employee = $assignment->employee;
            if ($employee?->user_id) {
                $otherActive = WorkforceAssignment::where('employee_id', $employee->id)
                    ->where('module_id', $assignment->module_id)
                    ->where('status', 'active')
                    ->where('id', '!=', $assignment->id)
                    ->exists();

                if (!$otherActive) {
                    DB::table('user_modules')
                        ->where('user_id', $employee->user_id)
                        ->where('module_id', $assignment->module_id)
                        ->delete();
                }
            }

            AuditService::log('workforce.unassigned', $assignment, $before, [
                'reason' => $reason,
            ]);

            // FCM
            if ($employee) {
                static::pushAssignment($employee, $assignment->module, 'unassigned');
            }
        });

        return ['error' => null];
    }

    /**
     * Suspend an assignment temporarily.
     */
    public static function suspend(WorkforceAssignment $assignment, string $reason): array
    {
        if (!$assignment->isActive()) {
            return ['error' => 'Only active assignments can be suspended.'];
        }

        $before = $assignment->only('status');
        $assignment->update(['status' => 'suspended', 'notes' => $reason]);
        AuditService::log('workforce.suspended', $assignment, $before, ['reason' => $reason]);

        return ['error' => null];
    }

    /**
     * Reactivate a suspended assignment.
     */
    public static function reactivate(WorkforceAssignment $assignment): array
    {
        if ($assignment->status !== 'suspended') {
            return ['error' => 'Only suspended assignments can be reactivated.'];
        }

        $before = $assignment->only('status');
        $assignment->update(['status' => 'active']);
        AuditService::log('workforce.reactivated', $assignment, $before, ['status' => 'active']);

        return ['error' => null];
    }

    /**
     * All active assignments for a module, with employee loaded.
     */
    public static function listByModule(int $moduleId)
    {
        return WorkforceAssignment::with(['employee.department', 'employee.position'])
            ->where('module_id', $moduleId)
            ->orderBy('status') // active first
            ->orderBy('assigned_at')
            ->get();
    }

    /**
     * All modules an employee is actively assigned to.
     */
    public static function employeeModules(HrEmployee $employee)
    {
        return WorkforceAssignment::with('module')
            ->where('employee_id', $employee->id)
            ->where('status', 'active')
            ->get();
    }

    // ── Private ──────────────────────────────────────────────────────────────

    private static function pushAssignment(HrEmployee $employee, ?Module $module, string $action): void
    {
        try {
            $employee->loadMissing('user');
            $token = $employee->user?->fcm_token;
            if (!$token || !$module) return;

            if ($action === 'assigned') {
                FcmService::sendToToken(
                    fcmToken: $token,
                    title:    '📋 New Module Assignment',
                    body:     "You have been assigned to {$module->name}.",
                    data:     ['type' => 'workforce_assigned', 'module' => $module->slug],
                );
            } else {
                FcmService::sendToToken(
                    fcmToken: $token,
                    title:    '📋 Module Assignment Ended',
                    body:     "Your assignment to {$module->name} has ended.",
                    data:     ['type' => 'workforce_unassigned', 'module' => $module->slug],
                );
            }
        } catch (\Throwable) {}
    }
}
