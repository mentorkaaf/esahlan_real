<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HrAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('hr')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('hr.login')->with('error', 'Please login to access the HR panel.');
        }

        $staff = Auth::guard('hr')->user();

        if (!$staff->is_active) {
            Auth::guard('hr')->logout();
            return redirect()->route('hr.login')->with('error', 'Your account has been deactivated. Contact the administrator.');
        }

        return $next($request);
    }
}
