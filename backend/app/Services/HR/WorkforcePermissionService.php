<?php

namespace App\Services\HR;

use App\Models\HR\HrEmployee;
use App\Models\ModuleRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Manages the sync between module role assignments and user_permissions.
 *
 * When an employee is assigned a module role, that role's permissions are
 * granted (inserted) into user_permissions (granted=1).
 *
 * When the assignment ends or the module role changes, the old module's
 * permissions are revoked (deleted from user_permissions where
 * permission.module = that module slug) and new ones inserted.
 *
 * This integrates cleanly with User::hasPermission() which checks
 * user_permissions first, then falls back to role_permissions.
 */
class WorkforcePermissionService
{
    /**
     * Grant all permissions from a module role to a user.
     *
     * @param  User       $user
     * @param  ModuleRole $moduleRole  The role to inherit permissions from.
     */
    public static function grantModuleRolePermissions(User $user, ModuleRole $moduleRole): void
    {
        $permIds = DB::table('module_role_permissions')
            ->where('module_role_id', $moduleRole->id)
            ->pluck('permission_id')
            ->all();

        if (empty($permIds)) {
            return;
        }

        // Build rows for user_permissions, skip already-granted ones
        $existing = DB::table('user_permissions')
            ->where('user_id', $user->id)
            ->whereIn('permission_id', $permIds)
            ->pluck('permission_id')
            ->flip()
            ->all();

        $rows = [];
        $now  = now();

        foreach ($permIds as $pid) {
            if (!isset($existing[$pid])) {
                $rows[] = [
                    'user_id'       => $user->id,
                    'permission_id' => $pid,
                    'granted'       => true,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }
        }

        if ($rows) {
            DB::table('user_permissions')->insertOrIgnore($rows);
        }
    }

    /**
     * Revoke all module-scoped permissions for a given module slug from a user.
     *
     * Called when an assignment ends so stale module permissions are cleaned up.
     * Permissions coming from other active assignments of the same module are left.
     *
     * @param  User   $user
     * @param  string $moduleSlug  e.g. 'efood'
     */
    public static function revokeModulePermissions(User $user, string $moduleSlug): void
    {
        // Find all permission IDs scoped to this module
        $permIds = DB::table('permissions')
            ->where('module', $moduleSlug)
            ->pluck('id')
            ->all();

        if (empty($permIds)) {
            return;
        }

        DB::table('user_permissions')
            ->where('user_id', $user->id)
            ->whereIn('permission_id', $permIds)
            ->delete();
    }

    /**
     * Swap the permissions for an assignment when its module role changes.
     *
     * Revokes the old role's permissions (that are not shared by another
     * active assignment of the same module) and grants the new role's.
     *
     * @param  User        $user
     * @param  string      $moduleSlug
     * @param  ModuleRole  $newRole
     */
    public static function swapModuleRolePermissions(
        User       $user,
        string     $moduleSlug,
        ModuleRole $newRole,
    ): void {
        static::revokeModulePermissions($user, $moduleSlug);
        static::grantModuleRolePermissions($user, $newRole);
    }

    /**
     * Resolve the effective permissions for an employee within a module.
     *
     * Returns a flat array of permission slugs.
     *
     * @param  HrEmployee $employee
     * @param  string     $moduleSlug
     */
    public static function resolveForEmployee(HrEmployee $employee, string $moduleSlug): array
    {
        if (!$employee->user_id) {
            return [];
        }

        // user_permissions granted for this module (explicit overrides)
        $explicit = DB::table('user_permissions')
            ->join('permissions', 'permissions.id', '=', 'user_permissions.permission_id')
            ->where('user_permissions.user_id', $employee->user_id)
            ->where('permissions.module', $moduleSlug)
            ->select('permissions.slug', 'user_permissions.granted')
            ->get();

        $granted = [];
        foreach ($explicit as $row) {
            if ($row->granted) {
                $granted[$row->slug] = true;
            }
        }

        return array_keys($granted);
    }

    /**
     * Check if a user has a specific module permission.
     * Checks user_permissions first (explicit), then module_role_permissions
     * via active workforce assignment.
     *
     * @param  User   $user
     * @param  string $permSlug  e.g. 'efood.orders.manage'
     */
    public static function userHasPermission(User $user, string $permSlug): bool
    {
        // 1. Check explicit user_permissions
        $explicit = DB::table('user_permissions')
            ->join('permissions', 'permissions.id', '=', 'user_permissions.permission_id')
            ->where('user_permissions.user_id', $user->id)
            ->where('permissions.slug', $permSlug)
            ->value('user_permissions.granted');

        if ($explicit !== null) {
            return (bool) $explicit;
        }

        // 2. Check via workforce assignments and their module roles
        $permId = DB::table('permissions')->where('slug', $permSlug)->value('id');
        if (!$permId) {
            return false;
        }

        $employee = $user->employee ?? null;
        if (!$employee) {
            // Try to find via user_id FK
            $employee = \App\Models\HR\HrEmployee::where('user_id', $user->id)->first();
        }
        if (!$employee) {
            return false;
        }

        return DB::table('workforce_assignments')
            ->join('module_role_permissions', 'module_role_permissions.module_role_id', '=', 'workforce_assignments.module_role_id')
            ->where('workforce_assignments.employee_id', $employee->id)
            ->where('workforce_assignments.status', 'active')
            ->whereNotNull('workforce_assignments.module_role_id')
            ->where('module_role_permissions.permission_id', $permId)
            ->exists();
    }

    /**
     * Revoke ALL module permissions and module memberships for an employee.
     *
     * Called when:
     *   - Employee is terminated
     *   - Employee is globally suspended
     *   - All workforce assignments are force-ended
     *
     * @param  HrEmployee $employee
     * @return int  Number of module slugs affected
     */
    public static function revokeAllEmployeeAccess(HrEmployee $employee): int
    {
        if (!$employee->user_id) {
            return 0;
        }

        $user = \App\Models\User::find($employee->user_id);
        if (!$user) {
            return 0;
        }

        // Find all module slugs the employee has access to
        $moduleSlugs = DB::table('user_modules')
            ->join('modules', 'modules.id', '=', 'user_modules.module_id')
            ->where('user_modules.user_id', $user->id)
            ->pluck('modules.slug')
            ->all();

        // Revoke all module-scoped user_permissions
        $modulePermIds = DB::table('permissions')
            ->whereIn('module', $moduleSlugs)
            ->pluck('id')
            ->all();

        if ($modulePermIds) {
            DB::table('user_permissions')
                ->where('user_id', $user->id)
                ->whereIn('permission_id', $modulePermIds)
                ->delete();
        }

        // Remove from user_modules
        DB::table('user_modules')
            ->where('user_id', $user->id)
            ->delete();

        return count($moduleSlugs);
    }

    /**
     * Check whether an employee has a valid, active, non-expired, non-suspended
     * assignment to a given module slug.
     *
     * Used by CheckModuleAccess middleware for API enforcement.
     */
    public static function employeeHasModuleAccess(HrEmployee $employee, string $moduleSlug): bool
    {
        return DB::table('workforce_assignments')
            ->join('modules', 'modules.id', '=', 'workforce_assignments.module_id')
            ->where('workforce_assignments.employee_id', $employee->id)
            ->where('modules.slug', $moduleSlug)
            ->where('workforce_assignments.status', 'active')
            ->where(function ($q) {
                $q->whereNull('workforce_assignments.planned_end_date')
                  ->orWhere('workforce_assignments.planned_end_date', '>=', now()->toDateString());
            })
            ->exists();
    }
}
