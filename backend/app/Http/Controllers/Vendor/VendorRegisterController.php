<?php
namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

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

        $modules = Module::whereIn('slug', self::VENDOR_MODULES)->get();
        return view('vendor.auth.register', compact('modules'));
    }

    public function register(Request $request)
    {
        $request->validate([
            'name'            => 'required|string|max:100',
            'phone'           => 'required|string|max:20|unique:users,phone',
            'pin'             => 'required|digits:4',
            'pin_confirmation'=> 'required|same:pin',
            'store_name'      => 'required|string|max:200',
            'store_description'=> 'nullable|string|max:500',
            'module_id'       => 'required|exists:modules,id',
        ], [
            'phone.unique'         => 'This phone number is already registered.',
            'pin.digits'           => 'PIN must be exactly 4 digits.',
            'pin_confirmation.same'=> 'PINs do not match.',
            'module_id.required'   => 'Please select the module you want to work with.',
        ]);

        $module = Module::findOrFail($request->module_id);

        // Only allow vendor modules
        if (!in_array($module->slug, self::VENDOR_MODULES)) {
            return back()->withErrors(['module_id' => 'Invalid module selected.'])->withInput();
        }

        // Normalize phone — ensure it starts with +252
        $phone = preg_replace('/\D/', '', $request->phone);
        if (!str_starts_with($phone, '252')) {
            // If user typed just local digits like 612345678
            if (strlen($phone) <= 9) {
                $phone = '252' . $phone;
            }
        }
        $phone = '+' . ltrim($phone, '+');

        // Check again after normalization
        if (User::where('phone', $phone)->exists()) {
            return back()->withErrors(['phone' => 'This phone number is already registered.'])->withInput();
        }

        DB::transaction(function () use ($request, $module, $phone) {
            $vendorOwnerRoleId = DB::table('roles')->where('slug', 'vendor_owner')->value('id');

            $user = User::create([
                'name'     => $request->name,
                'phone'    => $phone,
                'password' => Hash::make($request->pin),
                'role_id'  => $vendorOwnerRoleId,
            ]);

            Vendor::create([
                'user_id'     => $user->id,
                'module_id'   => $module->id,
                'module_slug' => $module->slug,
                'name'        => $request->store_name,
                'description' => $request->store_description,
                'status'      => 'pending',
                'is_approved' => false,
                'is_active'   => false,
                'is_open'     => false,
            ]);
        });

        return redirect()->route('vendor.login')
            ->with('register_success', true)
            ->with('success', 'Registration submitted! Your store application is under review. Admin will approve within 24 hours. You can log in once approved.');
    }
}
