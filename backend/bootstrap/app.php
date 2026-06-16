<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        App\Providers\AppServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Override the default Authenticate middleware to redirect to admin.login
        $middleware->redirectGuestsTo(fn () => route('admin.login'));

        $middleware->alias([
            'role'          => \App\Http\Middleware\RoleMiddleware::class,
            'module.active' => \App\Http\Middleware\CheckModuleActive::class,
            'vendor'        => \App\Http\Middleware\VendorMiddleware::class,
            'admin.gate'    => \App\Http\Middleware\AdminModuleGate::class,
        ]);

        // CORS must run before everything — prepend to global stack
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);

        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }
            // Admin routes → redirect to admin login
            if ($request->is('admin/*') || $request->is('admin')) {
                return redirect()->route('admin.login');
            }
            // Vendor routes → redirect to vendor login
            if ($request->is('vendor/*') || $request->is('vendor')) {
                return redirect()->route('vendor.login');
            }
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'Resource not found.'], 404);
            }
        });
    })->create();
