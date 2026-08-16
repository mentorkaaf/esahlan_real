<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateEmployee
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::guard('employee')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('employee.login')
                ->with('error', 'Fadlan gal si aad u gasho portal-ka.');
        }

        $employee = Auth::guard('employee')->user();

        // Block terminated / resigned employees
        if (in_array($employee->status, ['terminated', 'resigned'])) {
            Auth::guard('employee')->logout();
            $request->session()->invalidate();
            return redirect()->route('employee.login')
                ->withErrors(['login' => 'Akoon-kaagu xidnaa. Xiriir HR.']);
        }

        // Suspended: read-only warning (don't block completely)
        if ($employee->status === 'suspended') {
            $request->attributes->set('employee_suspended', true);
        }

        return $next($request);
    }
}
