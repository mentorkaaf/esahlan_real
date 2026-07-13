<?php
namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Services\SecurityAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class AdminAuthController extends Controller
{
    // Max 10 failed attempts per IP per 15 minutes → block 30 min
    const MAX_ATTEMPTS    = 10;
    const DECAY_MINUTES   = 15;
    const LOCKOUT_MINUTES = 30;

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $ip  = $request->ip();
        $key = 'admin_login:' . $ip;

        // Check if IP is locked out
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            SecurityAuditService::log('admin.login.blocked', 'critical', array_merge(
                SecurityAuditService::fromRequest($request),
                ['identifier' => $request->input('email'), 'reason' => 'rate_limit', 'retry_after' => $seconds]
            ));
            return back()->withErrors(['email' => 'Too many attempts. Try again in ' . ceil($seconds / 60) . ' minutes.']);
        }

        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, self::DECAY_MINUTES * 60);

            SecurityAuditService::log('admin.login.failed', 'warn', array_merge(
                SecurityAuditService::fromRequest($request),
                ['identifier' => $request->input('email'), 'reason' => 'invalid_credentials',
                 'attempts'   => RateLimiter::attempts($key)]
            ));

            return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
        }

        $user       = Auth::user();
        $adminRoles = ['super_admin', 'admin', 'operations_manager', 'finance_manager', 'marketing_manager', 'customer_support'];

        if (!in_array($user->role?->slug, $adminRoles)) {
            Auth::logout();
            SecurityAuditService::log('admin.access.denied', 'warn', array_merge(
                SecurityAuditService::fromRequest($request),
                ['identifier' => $user->email, 'reason' => 'insufficient_role', 'role' => $user->role?->slug]
            ));
            return back()->withErrors(['email' => 'You do not have admin access.']);
        }

        RateLimiter::clear($key);
        SecurityAuditService::log('admin.login.success', 'ok', array_merge(
            SecurityAuditService::fromRequest($request),
            ['user_id' => $user->id, 'identifier' => $user->email]
        ));

        $request->session()->regenerate();
        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            SecurityAuditService::log('admin.logout', 'info', array_merge(
                SecurityAuditService::fromRequest($request),
                ['user_id' => $user->id, 'identifier' => $user->email]
            ));
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
