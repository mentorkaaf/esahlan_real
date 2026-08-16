<?php

namespace App\Http\Middleware;

use App\Models\HR\HrEmployee;
use App\Services\HR\WorkforcePermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Middleware: module.access
 *
 * Enforces workforce module isolation at the API layer.
 *
 * Checks that the authenticated user has an active, non-suspended,
 * non-expired workforce assignment to the requested module.
 *
 * Usage in routes:
 *   ->middleware('module.access:efood')         # explicit slug
 *   ->middleware('module.access')               # reads from route {slug} or request
 *
 * Security guarantees:
 *   - eFood employee → eHealth API  → 403 Forbidden
 *   - eFood employee → eExchange API → 403 Forbidden (unless explicitly assigned)
 *   - Suspended assignment → 403 Forbidden
 *   - Expired assignment (planned_end_date past) → 403 Forbidden
 *   - Terminated employee → 403 Forbidden
 *   - Globally suspended employee → 403 Forbidden
 *
 * Super-admins (role: super_admin) bypass this check.
 * Users without a linked HrEmployee record bypass (non-HR users).
 */
class CheckModuleAccess
{
    public function handle(Request $request, Closure $next, ?string $moduleSlug = null): mixed
    {
        $user = Auth::user() ?? Auth::guard('sanctum')->user();

        if (!$user) {
            return $this->deny($request, 'Unauthenticated.', 401);
        }

        // Super-admins bypass module isolation
        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        // Resolve the module slug — param > route {slug} > request input
        $slug = $moduleSlug
            ?? $request->route('slug')
            ?? $request->route('module')
            ?? $request->input('module');

        if (!$slug) {
            // No slug to check against — pass through
            return $next($request);
        }

        // Find linked HrEmployee
        $employee = $this->resolveEmployee($user);

        if (!$employee) {
            // User has no HR profile — not subject to workforce access control
            return $next($request);
        }

        // Check employee is not globally deactivated
        if (in_array($employee->status, ['terminated', 'suspended', 'resigned'])) {
            return $this->deny($request,
                "Your account is {$employee->status}. Module access denied.",
                403
            );
        }

        // Check active assignment to the requested module
        if (!WorkforcePermissionService::employeeHasModuleAccess($employee, $slug)) {
            return $this->deny($request,
                "Access denied: you are not assigned to module '{$slug}'.",
                403
            );
        }

        // Attach resolved employee to request for downstream use
        $request->attributes->set('hr_employee', $employee);

        return $next($request);
    }

    private function resolveEmployee(\App\Models\User $user): ?HrEmployee
    {
        // Try relation first (if loaded), then query
        if (isset($user->employee) && $user->employee instanceof HrEmployee) {
            return $user->employee;
        }
        return HrEmployee::where('user_id', $user->id)->first();
    }

    private function deny(Request $request, string $message, int $status): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'error'   => 'module_access_denied',
            ], $status);
        }

        abort($status, $message);
    }
}
