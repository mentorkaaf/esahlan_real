<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckBanned
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) return $next($request);

        if ($user->status === 'banned') {
            if ($request->bearerToken()) {
                $user->currentAccessToken()?->delete();
            }
            return response()->json([
                'success' => false,
                'message' => 'Your account has been suspended.',
                'error'   => 'account_banned',
            ], 403);
        }

        // Restricted users can browse but cannot post/upload content
        if ($user->status === 'restricted') {
            $blockedMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
            $blockedPaths   = ['community/posts', 'community/stories', 'community/reels'];
            $path = $request->path();
            $isWrite = in_array($request->method(), $blockedMethods);
            $isCommunityPost = collect($blockedPaths)->contains(fn($p) => str_contains($path, $p));

            if ($isWrite && $isCommunityPost) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akoonkaagu waa la xidhi doonaa sababtoo ah adigoo xadgubaaya shuruucda eSahlan.',
                    'error'   => 'account_restricted',
                ], 403);
            }
        }

        return $next($request);
    }
}
