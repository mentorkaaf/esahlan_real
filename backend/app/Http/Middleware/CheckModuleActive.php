<?php
namespace App\Http\Middleware;

use App\Models\Module;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleActive
{
    public function handle(Request $request, Closure $next, string $moduleSlug): Response
    {
        $module = Module::where('slug', $moduleSlug)->where('is_active', true)->first();

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'This service module is currently unavailable.',
            ], 503);
        }

        $request->merge(['current_module' => $module]);

        return $next($request);
    }
}
