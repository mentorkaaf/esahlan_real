<?php
namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Module;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Mail\WelcomeVendorMail;
use Illuminate\Support\Facades\Mail;

class VendorApiRegisterController extends Controller
{
    // role_type → module slug (null = rent_agent, no vendor record)
    private const ROLE_MAP = [
        'eshop' => 'eshop',
        'efood' => 'efood',
        'erent_agent' => null,
    ];

    public function districts()
    {
        return response()->json([
            'success' => true,
            'data'    => District::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'role_type'   => 'required|in:eshop,efood,erent_agent',
            'name'        => 'required|string|max:100',
            'email'       => 'nullable|email|max:150',
            'phone'       => 'nullable|string|max:20',
            'password'    => 'required|string|min:8',
            'district_id' => 'required|exists:districts,id',
            // vendor-only fields
            'store_name'        => 'required_unless:role_type,erent_agent|nullable|string|max:200',
            'store_description' => 'nullable|string|max:500',
            'store_address'     => 'nullable|string|max:500',
            'latitude'          => 'nullable|numeric',
            'longitude'         => 'nullable|numeric',
            'business_license'  => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if (empty($request->email) && empty($request->phone)) {
            return response()->json(['success' => false, 'message' => 'Email or phone is required.'], 422);
        }

        // normalise phone
        $phone = null;
        if (!empty($request->phone)) {
            $phone = '+252' . preg_replace('/\D/', '', $request->phone);
        }

        // uniqueness checks
        if ($request->email && User::where('email', $request->email)->exists()) {
            return response()->json(['success' => false, 'message' => 'Email already registered.'], 422);
        }
        if ($phone && User::where('phone', $phone)->exists()) {
            return response()->json(['success' => false, 'message' => 'Phone number already registered.'], 422);
        }

        $roleType    = $request->role_type;
        $moduleSlug  = self::ROLE_MAP[$roleType];
        $isAgent     = $roleType === 'erent_agent';

        $roleSlug = $isAgent ? 'rent_agent' : 'vendor_owner';
        $roleId   = DB::table('roles')->where('slug', $roleSlug)->value('id');

        if (!$roleId) {
            return response()->json(['success' => false, 'message' => 'Role configuration error. Contact support.'], 500);
        }

        // license upload
        $licensePath = null;
        if ($request->hasFile('business_license')) {
            $licensePath = $request->file('business_license')->store('vendors/licenses', 'public');
        }

        DB::transaction(function () use ($request, $phone, $roleId, $moduleSlug, $isAgent, $licensePath) {
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email ?: null,
                'phone'    => $phone,
                'password' => Hash::make($request->password),
                'role_id'  => $roleId,
            ]);

            if (!$isAgent) {
                $module = Module::where('slug', $moduleSlug)->first();
                Vendor::create([
                    'user_id'          => $user->id,
                    'module_id'        => $module?->id,
                    'module_slug'      => $moduleSlug,
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
            }
        });

        if ($request->email) {
            try { Mail::to($request->email)->send(new WelcomeVendorMail($request->name)); } catch (\Exception) {}
        }

        $msg = $isAgent
            ? 'Agent account submitted! Admin will review and approve your application within 24 hours.'
            : 'Store registration submitted! Admin will approve your store within 24 hours.';

        return response()->json(['success' => true, 'message' => $msg]);
    }
}
