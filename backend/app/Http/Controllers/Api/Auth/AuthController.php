<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $v = Validator::make($request->all(), [
            'name'     => 'required|string|max:100',
            'phone'    => 'required|string|unique:users,phone',
            'password' => 'required|string|min:4|confirmed',
            'referral_code' => 'nullable|string|exists:users,referral_code',
            'district_id'   => 'nullable|integer|exists:districts,id',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $customerRole = DB::table('roles')->where('slug', 'customer')->first();

        $user = DB::transaction(function () use ($request, $customerRole) {
            $user = User::create([
                'uuid'            => (string) Str::uuid(),
                'name'            => $request->name,
                'phone'           => $request->phone,
                'email'           => $request->email,
                'password'        => Hash::make($request->password),
                'wallet_pin'      => Hash::make($request->password),
                'role_id'         => $customerRole?->id,
                'status'          => 'active',
                'referral_code'   => strtoupper(Str::random(8)),
                'preferred_language' => $request->language ?? 'so',
                'district_id'     => $request->district_id,
            ]);

            // Create wallet
            Wallet::create([
                'owner_type' => User::class,
                'owner_id'   => $user->id,
                'balance'    => 0,
                'currency'   => 'USD',
            ]);

            // Handle referral
            if ($request->referral_code) {
                $referrer = User::where('referral_code', $request->referral_code)->first();
                if ($referrer) {
                    DB::table('referrals')->insert([
                        'referrer_id' => $referrer->id,
                        'referred_id' => $user->id,
                        'status'      => 'pending',
                        'created_at'  => now(),
                    ]);
                }
            }

            return $user;
        });

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data'    => [
                'user'  => $user->load('role'),
                'token' => $token,
            ],
        ], 201);
    }

    public function sendOtp(Request $request)
    {
        $v = Validator::make($request->all(), [
            'phone'   => 'required|string',
            'purpose' => 'required|in:register,login,reset_password',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Expire old OTPs
        OtpCode::where('phone', $request->phone)->where('type', $request->purpose)->delete();

        OtpCode::create([
            'phone'      => $request->phone,
            'code'       => $code,
            'type'       => $request->purpose,
            'expires_at' => now()->addMinutes(10),
        ]);

        // TODO: send via SMS gateway (Hormuud/Somtel)
        // For testing, return code in response
        $responseData = ['message' => 'OTP sent successfully'];
        if (app()->isLocal()) {
            $responseData['code'] = $code; // Only in dev/local
        }

        return response()->json(['success' => true, ...$responseData]);
    }

    public function verifyOtp(Request $request)
    {
        $v = Validator::make($request->all(), [
            'phone'   => 'required|string',
            'code'    => 'required|string|size:6',
            'purpose' => 'required|in:register,login,reset_password',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $otp = OtpCode::where('phone', $request->phone)
            ->where('code', $request->code)
            ->where('type', $request->purpose)
            ->where('expires_at', '>', now())
            ->whereNull('used_at')
            ->first();

        if (!$otp) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired OTP'], 422);
        }

        $otp->update(['used_at' => now()]);

        // Mark phone as verified
        if ($request->purpose === 'register' || $request->purpose === 'login') {
            User::where('phone', $request->phone)->update(['phone_verified_at' => now()]);
        }

        return response()->json(['success' => true, 'message' => 'OTP verified']);
    }

    public function login(Request $request)
    {
        $v = Validator::make($request->all(), [
            'phone'    => 'required_without:email|string',
            'email'    => 'required_without:phone|email',
            'password' => 'required|string',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $field = $request->has('email') ? 'email' : 'phone';
        $user  = User::where($field, $request->$field)->with('role')->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }

        if ($user->status === 'banned') {
            return response()->json(['success' => false, 'message' => 'Your account has been banned'], 403);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => ['user' => $user, 'token' => $token],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data'    => $request->user()->load('role', 'wallet', 'district'),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $v = Validator::make($request->all(), [
            'name'     => 'sometimes|string|max:100',
            'email'    => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:6|confirmed',
            'language' => 'sometimes|in:so,en,ar',
            'avatar'   => 'sometimes|image|max:2048',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $data = $request->only('name', 'email');
        if ($request->language) $data['preferred_language'] = $request->language;
        if ($request->password) $data['password'] = Hash::make($request->password);

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = $path;
        }

        $user->update($data);

        return response()->json(['success' => true, 'data' => $user->fresh()]);
    }

    public function forgotPassword(Request $request)
    {
        $v = Validator::make($request->all(), ['phone' => 'required|string|exists:users,phone']);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        // Reuse sendOtp logic with purpose=reset_password
        $request->merge(['purpose' => 'reset_password']);
        return $this->sendOtp($request);
    }

    public function resetPassword(Request $request)
    {
        $v = Validator::make($request->all(), [
            'phone'    => 'required|string|exists:users,phone',
            'code'     => 'required|string|size:6',
            'password' => 'required|string|min:4|confirmed',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $otp = OtpCode::where('phone', $request->phone)
            ->where('code', $request->code)
            ->where('type', 'reset_password')
            ->where('expires_at', '>', now())
            ->whereNotNull('used_at')
            ->first();

        if (!$otp) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired OTP. Please verify OTP first.'], 422);
        }

        User::where('phone', $request->phone)->update([
            'password'   => Hash::make($request->password),
            'wallet_pin' => Hash::make($request->password),
        ]);
        $otp->delete();

        return response()->json(['success' => true, 'message' => 'Password reset successfully']);
    }

    public function deleteAccount(Request $request)
    {
        $request->user()->update(['status' => 'deleted']);
        $request->user()->tokens()->delete();
        return response()->json(['success' => true, 'message' => 'Account deleted']);
    }

    public function updateFcmToken(Request $request)
    {
        $request->validate(['fcm_token' => 'required|string']);
        $request->user()->update(['fcm_token' => $request->fcm_token]);
        return response()->json(['success' => true, 'message' => 'FCM token updated']);
    }

    public function updateLocation(Request $request)
    {
        $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);
        $request->user()->update([
            'latitude'            => $request->latitude,
            'longitude'           => $request->longitude,
            'location_updated_at' => now(),
        ]);
        return response()->json(['success' => true]);
    }
}
