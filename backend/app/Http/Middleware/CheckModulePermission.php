<?php

namespace App\Http\Middleware;

use App\Services\HR\WorkforcePermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Middleware: module.permission
 *
 * Checks whether the authenticated user holds a specific module permission slug.
 *
 * Usage in routes:
 *   ->middleware('module.permission:efood.orders.manage')
 *
 * The slug is in the format:  {module}.{group}.{action}
 *
 * Resolution order:
 *   1. user_permissions (explicit overrides — highest precedence)
 *   2. module_role_permissions via active workforce_assignments
 *   3. role_permissions (system role fallback)
 */
class CheckModulePermission
{
    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        $user = Auth::user() ?? Auth::guard('sanctum')->user();

        if (!$user) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401)
                : redirect()->route('admin.login');
        }

        // Use WorkforcePermissionService for module-aware check
        if (!WorkforcePermissionService::userHasPermission($user, $permission)) {
            // Also accept if the user has a system-level permission via role
            if (!$user->hasPermission($permission)) {
                return $request->expectsJson()
                    ? response()->json(['success' => false, 'message' => 'You do not have permission to perform this action.'], 403)
                    : abort(403, "Forbidden — required permission: {$permission}");
            }
        }

        return $next($request);
    }
}
