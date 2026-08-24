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
        // Auth endpoints — dual key: IP + identifier
        // Prevents distributed attacks (many IPs → one account)
        // and single-IP spray attacks (one IP → many accounts)
        RateLimiter::for('auth', function (Request $request) {
            $identifier = strtolower(trim(
                $request->input('phone') ?? $request->input('email') ?? 'unknown'
            ));
            return [
                // Per-IP: 10 attempts/min (spray attack protection)
                Limit::perMinute(10)->by('auth:ip:' . $request->ip())
                    ->response(fn () => response()->json([
                        'success' => false,
                        'message' => 'Too many requests from your network. Try again in a minute.',
                    ], 429)),
                // Per-identifier: 5 attempts/min (credential stuffing protection)
                Limit::perMinute(5)->by('auth:id:' . $identifier)
                    ->response(fn () => response()->json([
                        'success' => false,
                        'message' => 'Too many attempts for this account. Try again in a minute.',
                    ], 429)),
            ];
        });

        // OTP: dual key — IP + phone/email (SMS pumping + account enumeration protection)
        RateLimiter::for('otp', function (Request $request) {
            $identifier = strtolower(trim(
                $request->input('phone') ?? $request->input('email') ?? 'unknown'
            ));
            return [
                // Per-IP: 5 OTPs per 10 minutes
                Limit::perMinutes(10, 5)->by('otp:ip:' . $request->ip())
                    ->response(fn () => response()->json([
                        'success' => false,
                        'message' => 'Too many OTP requests from your network. Try again in 10 minutes.',
                    ], 429)),
                // Per-identifier: 3 OTPs per 10 minutes (strict — prevents SMS cost abuse)
                Limit::perMinutes(10, 3)->by('otp:id:' . $identifier)
                    ->response(fn () => response()->json([
                        'success' => false,
                        'message' => 'Too many OTP requests for this number. Try again in 10 minutes.',
                    ], 429)),
            ];
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
