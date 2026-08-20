<?php
namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VendorAuthController extends Controller
{
    public function showLogin()
    {
        if (auth()->check()) {
            $role = auth()->user()->role?->slug ?? '';
            if (in_array($role, ['vendor_owner', 'vendor_employee'])) {
                return redirect()->route('vendor.dashboard');
            }
        }
        return view('vendor.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
        ], [
            'login.required'    => 'Phone number or email is required.',
            'password.required' => 'Password is required.',
        ]);

        $login = trim($request->login);
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        // Normalize phone if needed
        if ($field === 'phone') {
            $digits = preg_replace('/\D/', '', $login);
            if (!str_starts_with($digits, '252') && strlen($digits) <= 9) {
                $digits = '252' . $digits;
            }
            $login = '+' . ltrim($digits, '+');
        }

        $credentials = [
            $field     => $login,
            'password' => $request->password,
        ];

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['login' => 'Invalid phone/email or password.'])->withInput();
        }

        $user = auth()->user();
        $role = $user->role?->slug ?? '';

        if (!in_array($role, ['vendor_owner', 'vendor_employee'])) {
            Auth::logout();
            return back()->withErrors(['phone' => 'Bu hesap vendor paneline erişim yetkisine sahip değil.'])->withInput();
        }

        if (!$user->vendor) {
            Auth::logout();
            return back()->withErrors(['phone' => 'Vendor profili bulunamadı. Lütfen admin ile iletişime geçin.'])->withInput();
        }

        $request->session()->regenerate();
        $moduleSlug = $user->vendor?->module_slug ?? '';
        if ($moduleSlug === 'ewholesale') {
            return redirect()->intended(route('vendor.wholesale.dashboard'));
        }
        if ($moduleSlug === 'eshop') {
            return redirect()->intended(route('vendor.eshop.dashboard'));
        }
        return redirect()->intended(route('vendor.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('vendor.login');
    }
}
