<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckBanned
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $user->status === 'banned') {
            // Revoke current token so the client is forced to re-authenticate
            if ($request->bearerToken()) {
                $user->currentAccessToken()?->delete();
            }
            return response()->json([
                'success' => false,
                'message' => 'Your account has been suspended.',
                'error'   => 'account_banned',
            ], 403);
        }
        return $next($request);
    }
}
