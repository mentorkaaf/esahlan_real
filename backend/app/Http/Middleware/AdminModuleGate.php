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
    /** admin path prefix => module slug it manages. */
    private array $map = [
        'admin/module-data/efood'     => 'efood',
        'admin/module-data/shop'      => 'eshop',
        'admin/module-data/wholesale' => 'ewholesale',
        'admin/module-data/grocery'   => 'egrocery',
        'admin/module-data/laundry'   => 'elaundry',
        'admin/module-data/moving'    => 'emoving',
        'admin/module-data/parcel'    => 'eparcel',
        'admin/module-data/data'      => 'edata',
        'admin/module-data/exchange'  => 'eexchange',
        'admin/module-data/health'    => 'ehealth',
        'admin/module-data/rent'      => 'erent',
        'admin/module-data/ticket'    => 'eticket',
        'admin/eshop'                 => 'eshop',
        'admin/exchange'              => 'eexchange',
        'admin/elearning'             => 'elearning',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Full admins (and anything that isn't an employee) are unrestricted here;
        // role:* middleware already gated who reaches this point.
        if (!$user || !$user->isEmployee()) {
            return $next($request);
        }

        $path = $request->path(); // e.g. "admin/module-data/efood/items"

        // Always allow the landing dashboard + logout.
        if ($path === 'admin'
            || $path === 'admin/dashboard'
            || str_starts_with($path, 'admin/logout')) {
            return $next($request);
        }

        foreach ($this->map as $prefix => $slug) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                if ($user->canManageModule($slug)) {
                    return $next($request);
                }
                abort(403, 'You are not assigned to manage this module.');
            }
        }

        // Any other admin page is off-limits to a module employee.
        abort(403, 'Employees can only access their assigned modules.');
    }
}
