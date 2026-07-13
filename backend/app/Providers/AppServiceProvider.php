<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();
        $this->defineGates();
    }

    private function defineGates(): void
    {
        // super_admin bypasses every gate check automatically
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('super_admin')) return true;
            return null; // let the specific gate decide
        });

        // Define a gate for every permission slug — delegates to User::hasPermission()
        $permissions = [
            // Community
            'community.post.create',
            'community.post.delete_any',
            'community.comment.delete_any',
            'community.user.ban',
            'community.report.view',
            'community.report.resolve',
            'community.content.feature',
            // Finance
            'finance.transaction.view',
            'finance.refund.process',
            'finance.withdrawal.approve',
            // Platform
            'platform.setting.manage',
            'platform.role.manage',
            'platform.audit.view',
            'platform.analytics.view',
            'platform.module.manage',
        ];

        foreach ($permissions as $slug) {
            Gate::define($slug, fn (User $user) => $user->hasPermission($slug));
        }
    }

    private function configureRateLimiting(): void
    {
        // Auth endpoints (unauthenticated — keyed by IP)
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip())
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Slow down.',
                ], 429));
        });

        // OTP: stricter — 5 per 5 minutes per IP to prevent SMS pumping
        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinutes(5, 5)->by($request->ip())
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'Too many OTP requests. Try again in 5 minutes.',
                ], 429));
        });

        // Standard authenticated API calls
        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();
            return Limit::perMinute(300)->by('api:' . $key)
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'Rate limit exceeded.',
                ], 429));
        });

        // Feed + explore — heavier reads, limit per user
        RateLimiter::for('feed', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();
            return Limit::perMinute(60)->by('feed:' . $key)
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'Feed rate limit exceeded. Please wait.',
                ], 429));
        });

        // File upload — prevent bulk upload abuse
        RateLimiter::for('upload', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();
            return Limit::perMinute(20)->by('upload:' . $key)
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'Upload limit reached. Max 20 uploads per minute.',
                ], 429));
        });

        // Payment / wallet — strictest to prevent fraud
        RateLimiter::for('payment', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();
            return Limit::perMinute(10)->by('payment:' . $key)
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'Too many payment requests.',
                ], 429));
        });

        // Chat messages — prevent spam
        RateLimiter::for('chat', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();
            return Limit::perMinute(120)->by('chat:' . $key)
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'Slow down — too many messages.',
                ], 429));
        });

        // Media proxy — prevent enumeration / bandwidth abuse (unauthenticated, keyed by IP)
        RateLimiter::for('media', function (Request $request) {
            return Limit::perMinute(200)->by('media:' . $request->ip())
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'Too many media requests.',
                ], 429));
        });
    }
}
