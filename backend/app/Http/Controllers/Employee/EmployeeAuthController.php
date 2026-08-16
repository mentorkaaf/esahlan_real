<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\HR\HrEmployee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class EmployeeAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('employee')->check()) {
            return redirect()->route('employee.dashboard');
        }
        return view('employee.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);

        $login = trim($request->input('login'));

        // Find employee by phone, email, or employee_no
        $employee = HrEmployee::where('phone', $login)
            ->orWhere('email', $login)
            ->orWhere('employee_no', $login)
            ->first();

        if (! $employee) {
            return back()->withErrors(['login' => 'Macluumaadku saxna maahan.'])->withInput();
        }

        // Check terminated / resigned before even verifying password
        if (in_array($employee->status, ['terminated', 'resigned'])) {
            return back()->withErrors(['login' => 'Akoon-kaagu xidnaa. Xiriir HR.']);
        }

        $password = $request->input('password');

        // Try password first, then PIN fallback
        $valid = ($employee->password && Hash::check($password, $employee->password))
               || ($employee->pin && $password === $employee->pin);

        if (! $valid) {
            return back()->withErrors(['login' => 'Macluumaadku saxna maahan.'])->withInput();
        }

        // Login via employee guard
        Auth::guard('employee')->login($employee, $request->boolean('remember'));

        // Track login time + IP
        $employee->update([
            'login_at'       => now(),
            'last_login_ip'  => $request->ip(),
        ]);

        $request->session()->regenerate();

        return redirect()->intended(route('employee.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::guard('employee')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('employee.login');
    }
}
