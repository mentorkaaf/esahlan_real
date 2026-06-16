<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Scopes "employee" users to only the module-management areas they are assigned to.
 * Full admins (super_admin/admin/operations/finance/marketing/support) pass through.
 * Employees may only reach the dashboard and their assigned module pages.
 */
class AdminModuleGate
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Full admins pass through unrestricted.
        if (!$user || !$user->isEmployee()) {
            return $next($request);
        }

        $path = $request->path();

        // Redirect employees away from admin dashboard.
        if ($path === 'admin' || $path === 'admin/dashboard') {
            return redirect()->route('employee.dashboard');
        }

        // Logout is always allowed.
        if (str_starts_with($path, 'admin/logout')) {
            return $next($request);
        }

        // Employees may only access orders and deliverymen (controller scopes by module).
        if (str_starts_with($path, 'admin/orders') || str_starts_with($path, 'admin/deliverymen')) {
            return $next($request);
        }

        // Everything else is off-limits.
        abort(403, 'Employees can only access their assigned module orders.');
    }
}
