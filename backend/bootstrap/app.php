<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        App\Providers\AppServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->prefix('hr')
                ->name('hr.')
                ->group(base_path('routes/hr.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Override the default Authenticate middleware to redirect to admin.login
        $middleware->redirectGuestsTo(fn () => route('admin.login'));

        $middleware->alias([
            'role'             => \App\Http\Middleware\RoleMiddleware::class,
            'module.active'    => \App\Http\Middleware\CheckModuleActive::class,
            'vendor'           => \App\Http\Middleware\VendorMiddleware::class,
            'admin.gate'       => \App\Http\Middleware\AdminModuleGate::class,
            'brute_force'      => \App\Http\Middleware\CheckBruteForce::class,
            'admin.monitor'    => \App\Http\Middleware\AdminRouteMonitor::class,
            'auth.hr'          => \App\Http\Middleware\HrAuthenticate::class,
            'hr.role'          => \App\Http\Middleware\HrRole::class,
        ]);

        // Sanitize text input on every API request (strip null bytes + control chars)
        $middleware->api(append: [
            \App\Http\Middleware\SanitizeInput::class,
            \App\Http\Middleware\CheckBanned::class,
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
            // HR routes → redirect to HR login
            if ($request->is('hr/*') || $request->is('hr')) {
                return redirect()->route('hr.login');
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
