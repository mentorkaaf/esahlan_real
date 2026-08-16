<?php

namespace App\Services\HR;

use App\Models\HR\HrAuditLog;
use App\Models\HR\HrEmployee;
use App\Models\HR\WorkforceAssignment;
use App\Models\Module;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log an HR audit event.
     *
     * @param  string      $action      e.g. 'employee.created', 'workforce.assigned'
     * @param  Model|null  $subject     the model being acted on
     * @param  array|null  $before      state before change
     * @param  array|null  $after       state after change
     * @param  array       $extra       override actor info or add employee_id/module_id/category
     */
    public static function log(
        string $action,
        ?Model $subject = null,
        ?array $before  = null,
        ?array $after   = null,
        array  $extra   = []
    ): HrAuditLog {
        [$actorType, $actorId, $actorName] = static::resolveActor($extra);

        return HrAuditLog::create([
            'actor_type'   => $actorType,
            'actor_id'     => $actorId,
            'actor_name'   => $actorName,
            'action'       => $action,
            'category'     => $extra['category'] ?? static::inferCategory($action),
            'subject_type' => $subject ? get_class($subject) : ($extra['subject_type'] ?? null),
            'subject_id'   => $subject ? $subject->getKey() : ($extra['subject_id']   ?? null),
            'employee_id'  => $extra['employee_id'] ?? null,
            'module_id'    => $extra['module_id']   ?? null,
            'before'       => $before,
            'after'        => $after,
            'ip'           => Request::ip(),
            'user_agent'   => substr(Request::userAgent() ?? '', 0, 500),
            'created_at'   => now(),
        ]);
    }

    /**
     * Specialized workforce audit log — automatically extracts employee_id/module_id
     * from the assignment and enriches the before/after with human-readable labels.
     */
    public static function logWorkforce(
        string              $action,
        WorkforceAssignment $assignment,
        ?array              $before = null,
        ?array              $after  = null,
        array               $extra  = []
    ): HrAuditLog {
        $assignment->loadMissing(['employee', 'module']);

        return static::log($action, $assignment, $before, $after, array_merge([
            'category'    => 'workforce',
            'employee_id' => $assignment->employee_id,
            'module_id'   => $assignment->module_id,
        ], $extra));
    }

    /**
     * Log a workforce event on an employee without an assignment reference
     * (e.g. "all module access revoked on termination").
     */
    public static function logWorkforceEmployee(
        string     $action,
        HrEmployee $employee,
        ?array     $before = null,
        ?array     $after  = null,
        ?int       $moduleId = null,
        array      $extra  = []
    ): HrAuditLog {
        return static::log($action, $employee, $before, $after, array_merge([
            'category'    => 'workforce',
            'employee_id' => $employee->id,
            'module_id'   => $moduleId,
        ], $extra));
    }

    /**
     * Log an override action taken by a web/admin user (super_admin).
     */
    public static function logAdmin(
        string $action,
        ?Model $subject = null,
        ?array $before  = null,
        ?array $after   = null,
    ): HrAuditLog {
        $admin = Auth::guard('web')->user();
        return static::log($action, $subject, $before, $after, [
            'actor_type' => 'admin',
            'actor_id'   => $admin?->id,
            'actor_name' => $admin ? $admin->name . ' [ADMIN OVERRIDE]' : 'Admin',
        ]);
    }

    /**
     * Build a before/after diff array from model dirty attributes.
     */
    public static function diffModel(Model $model): array
    {
        $before = [];
        $after  = [];

        foreach ($model->getDirty() as $key => $newVal) {
            // Skip sensitive internal keys
            if (in_array($key, ['updated_at', 'created_at', 'remember_token'])) continue;
            $before[$key] = $model->getOriginal($key);
            $after[$key]  = $newVal;
        }

        return [$before ?: null, $after ?: null];
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * Resolve the current actor from auth guards.
     * Priority: hr guard → web (admin) guard → system
     */
    private static function resolveActor(array $extra): array
    {
        if (!empty($extra['actor_type'])) {
            return [$extra['actor_type'], $extra['actor_id'] ?? null, $extra['actor_name'] ?? 'System'];
        }

        if (Auth::guard('hr')->check()) {
            $staff = Auth::guard('hr')->user();
            return ['hr', $staff->id, $staff->name . ' (' . $staff->role_label . ')'];
        }

        if (Auth::guard('web')->check()) {
            $admin = Auth::guard('web')->user();
            return ['admin', $admin->id, $admin->name];
        }

        return ['system', null, 'System'];
    }

    private static function inferCategory(string $action): string
    {
        $prefix = explode('.', $action)[0];
        return match($prefix) {
            'workforce'   => 'workforce',
            'employee'    => 'employee',
            'payroll'     => 'payroll',
            'performance' => 'performance',
            'leave'       => 'leave',
            'attendance'  => 'attendance',
            'recruitment' => 'recruitment',
            default       => $prefix,
        };
    }
}
