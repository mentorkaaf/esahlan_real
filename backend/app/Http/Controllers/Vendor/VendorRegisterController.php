<?php
namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Module;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Mail\WelcomeVendorMail;
use Illuminate\Support\Facades\Mail;
use App\Models\EWholesale\EWSupplier;

class VendorRegisterController extends Controller
{
    private const VENDOR_MODULES = ['efood', 'eshop', 'egrocery', 'ewholesale'];

    public function showRegister()
    {
        if (auth()->check()) {
            $role = auth()->user()->role?->slug ?? '';
            if (in_array($role, ['vendor_owner', 'vendor_employee'])) {
                return redirect()->route('vendor.dashboard');
            }
        }

        $modules   = Module::whereIn('slug', self::VENDOR_MODULES)->get();
        $districts = District::orderBy('name')->get(['id', 'name']);
        return view('vendor.auth.register', compact('modules', 'districts'));
    }

    public function register(Request $request)
    {
        $request->validate([
            'name'              => 'required|string|max:100',
            'email'             => 'required|email|max:150|unique:users,email',
            'phone_full'        => 'required|string|max:20|unique:users,phone',
            'password'          => 'required|string|min:8|confirmed',
            'store_name'        => 'required|string|max:200',
            'store_description' => 'nullable|string|max:500',
            'district_id'       => 'required|exists:districts,id',
            'business_license'  => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'module_id'         => 'required|exists:modules,id',
            'latitude'          => 'nullable|numeric|between:-90,90',
            'longitude'         => 'nullable|numeric|between:-180,180',
            'store_address'     => 'nullable|string|max:500',
        ], [
            'email.required'          => 'Email address is required.',
            'email.unique'            => 'This email is already registered.',
            'phone_full.required'     => 'Phone number is required.',
            'phone_full.unique'       => 'This phone number is already registered.',
            'password.min'            => 'Password must be at least 8 characters.',
            'password.confirmed'      => 'Passwords do not match.',
            'district_id.required'    => 'Please select your district.',
            'module_id.required'      => 'Please select the module you want to work with.',
            'business_license.required' => 'Business license / government permit is required.',
            'business_license.mimes'    => 'Business license must be a JPG, PNG, or PDF file.',
            'business_license.max'      => 'Business license file must not exceed 5MB.',
        ]);

        $module = Module::findOrFail($request->module_id);

        if (!in_array($module->slug, self::VENDOR_MODULES)) {
            return back()->withErrors(['module_id' => 'Invalid module selected.'])->withInput();
        }

        // Phone comes pre-formatted as +252XXXXXXXXX from the form
        $phone = '+252' . preg_replace('/\D/', '', $request->phone_full);

        if (User::where('phone', $phone)->exists()) {
            return back()->withErrors(['phone_full' => 'This phone number is already registered.'])->withInput();
        }

        // Handle business license upload
        $licensePath = null;
        if ($request->hasFile('business_license')) {
            $licensePath = $request->file('business_license')
                ->store('vendors/licenses', 'public');
        }

        DB::transaction(function () use ($request, $module, $phone, $licensePath) {
            $vendorOwnerRoleId = DB::table('roles')->where('slug', 'vendor_owner')->value('id');

            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'phone'    => $phone,
                'password' => Hash::make($request->password),
                'role_id'  => $vendorOwnerRoleId,
            ]);

            $vendor = Vendor::create([
                'user_id'          => $user->id,
                'module_id'        => $module->id,
                'module_slug'      => $module->slug,
                'district_id'      => $request->district_id,
                'business_license' => $licensePath,
                'name'             => $request->store_name,
                'description'      => $request->store_description,
                'latitude'         => $request->latitude ?: null,
                'longitude'        => $request->longitude ?: null,
                'address'          => $request->store_address ?: null,
                'status'           => 'pending',
                'is_approved'      => false,
                'is_active'        => false,
                'is_open'          => true,
            ]);

            // eWholesale: auto-create supplier profile (pending admin approval)
            if ($module->slug === 'ewholesale') {
                EWSupplier::create([
                    'vendor_id'         => $vendor->id,
                    'display_name'      => $request->store_name,
                    'about'             => $request->store_description,
                    'warehouse_address' => $request->store_address,
                    'verification'      => 'pending',
                    'is_active'         => false,
                ]);
            }
        });

        $vendorEmail = $request->email;
        $vendorName  = $request->name;
        if ($vendorEmail) {
            try { Mail::to($vendorEmail)->send(new WelcomeVendorMail($vendorName)); } catch (\Exception) {}
        }

        // Admin alert
        try {
            \App\Services\AdminAlertService::send('new_vendor', "New Vendor Registration: {$request->store_name}", [
                'Store Name'  => $request->store_name,
                'Owner'       => $request->name,
                'Email'       => $request->email,
                'Phone'       => $request->phone_full,
                'Module'      => $module->name ?? 'N/A',
                'Status'      => 'Pending Approval',
                'Submitted'   => now()->format('d M Y H:i') . ' UTC',
            ]);
        } catch (\Throwable) {}

        return redirect()->route('vendor.login')
            ->with('register_success', true)
            ->with('success', 'Registration submitted! Your store application is under review. Admin will approve within 24 hours. You can log in once approved.');
    }
}
