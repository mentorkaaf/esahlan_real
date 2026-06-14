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
            'phone'    => 'required|string',
            'password' => 'required|string',
        ], [
            'phone.required'    => 'Telefon numarası gereklidir.',
            'password.required' => 'PIN gereklidir.',
        ]);

        $credentials = [
            'phone'    => $request->phone,
            'password' => $request->password,
        ];

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['phone' => 'Telefon numarası veya PIN hatalı.'])->withInput();
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
