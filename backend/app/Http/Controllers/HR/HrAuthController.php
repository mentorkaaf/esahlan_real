<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HrAuthController extends Controller
{
    public function showLogin()
    {
        return view('hr.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::guard('hr')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $staff = Auth::guard('hr')->user();
            $staff->update(['last_login_at' => now()]);

            \App\Services\HR\AuditService::log('auth.login', $staff);

            return redirect()->route('hr.dashboard');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        \App\Services\HR\AuditService::log('auth.logout', Auth::guard('hr')->user());

        Auth::guard('hr')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('hr.login');
    }
}
