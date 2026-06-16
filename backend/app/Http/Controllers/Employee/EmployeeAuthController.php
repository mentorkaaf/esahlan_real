<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeeAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check() && Auth::user()->isEmployee()) {
            return redirect()->route('employee.dashboard');
        }
        return view('employee.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login'    => 'required|string',   // phone OR email
            'password' => 'required|string',   // PIN OR password
        ]);

        $login = trim($request->input('login'));
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        // Resolve phone typed with/without +252 or a leading 0 to the stored value.
        if ($field === 'phone') {
            $digits     = preg_replace('/\D/', '', $login);
            $candidates = array_values(array_unique(array_filter([
                $login,
                '+' . $digits,
                $digits,
                ltrim($digits, '0'),
                '+252' . ltrim($digits, '0'),
                '+252' . preg_replace('/^252/', '', $digits),
            ])));
            $match = User::whereIn('phone', $candidates)->first();
            if ($match) {
                $login = $match->phone;
            }
        }

        if (!Auth::attempt([$field => $login, 'password' => $request->password], $request->boolean('remember'))) {
            return back()->withErrors(['login' => 'Invalid credentials.'])->withInput();
        }

        $user = Auth::user();

        // Must be an employee with at least one assigned module.
        if (!$user->isEmployee() || !$user->managedModules()->exists()) {
            Auth::logout();
            return back()->withErrors([
                'login' => 'This account is not an employee, or no module has been assigned to you yet.',
            ]);
        }

        $request->session()->regenerate();
        return redirect()->route('employee.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('employee.login');
    }
}
