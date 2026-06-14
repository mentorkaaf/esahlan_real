<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VendorMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return redirect()->route('vendor.login');
        }

        $role = auth()->user()->role?->slug ?? '';
        if (!in_array($role, ['vendor_owner', 'vendor_employee'])) {
            auth()->logout();
            return redirect()->route('vendor.login')->withErrors(['phone' => 'Bu hesap vendor paneline giriş yapamaz.']);
        }

        $vendor = auth()->user()->vendor;
        if (!$vendor) {
            auth()->logout();
            return redirect()->route('vendor.login')->withErrors(['phone' => 'Vendor profili bulunamadı. Lütfen admin ile iletişime geçin.']);
        }

        return $next($request);
    }
}
