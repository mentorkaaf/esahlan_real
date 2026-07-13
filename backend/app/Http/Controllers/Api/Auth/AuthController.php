<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginAttempt;
use App\Models\OtpCode;
use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use App\Services\FileUploadSecurityService;
use App\Services\SecurityAuditService;
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
        // If email provided → strong password required; otherwise 4-digit PIN allowed
        $passwordRule = $request->filled('email')
            ? ['required', 'string', 'min:8', 'confirmed',
               'regex:/[A-Z]/', 'regex:/[a-z]/', 'regex:/[0-9]/', 'regex:/[@$!%*#?&^_\-]/']
            : ['required', 'string', 'min:4', 'max:4', 'regex:/^\d{4}$/'];

        $v = Validator::make($request->all(), [
            'name'          => 'required|string|max:100',
            'phone'         => 'required|string|unique:users,phone',
            'email'         => 'nullable|email|unique:users,email',
            'password'      => $passwordRule,
            'referral_code' => 'nullable|string|exists:users,referral_code',
            'district_id'   => 'nullable|integer|exists:districts,id',
        ], [
            'password.regex' => 'Password must contain uppercase, lowercase, number and special character (@$!%*#?&).',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $customerRole = DB::table('roles')->where('slug', 'customer')->first();

        $user = DB::transaction(function () use ($request, $customerRole) {
            $user = User::create([
                'uuid'               => (string) Str::uuid(),
                'name'               => $request->name,
                'phone'              => $request->phone,
                'email'              => $request->email,
                'password'           => Hash::make($request->password),
                'wallet_pin'         => Hash::make($request->password),
                'role_id'            => $customerRole?->id,
                'status'             => 'active',
                'referral_code'      => strtoupper(Str::random(8)),
                'preferred_language' => $request->language ?? 'so',
                'district_id'        => $request->district_id,
            ]);

            Wallet::create([
                'owner_type' => User::class,
                'owner_id'   => $user->id,
                'balance'    => 0,
                'currency'   => 'USD',
            ]);

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

        SecurityAuditService::log('account.registered', 'info', array_merge(
            SecurityAuditService::fromRequest($request),
            ['user_id' => $user->id, 'identifier' => $user->phone]
        ));

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data'    => ['user' => $user->load('role'), 'token' => $token],
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

        OtpCode::where('phone', $request->phone)->where('type', $request->purpose)->delete();

        OtpCode::create([
            'phone'      => $request->phone,
            'code'       => $code,
            'type'       => $request->purpose,
            'expires_at' => now()->addMinutes(10),
        ]);

        SecurityAuditService::log('otp.sent', 'info', array_merge(
            SecurityAuditService::fromRequest($request),
            ['identifier' => $request->phone, 'purpose' => $request->purpose]
        ));

        $responseData = ['message' => 'OTP sent successfully'];
        if (app()->isLocal()) {
            $responseData['code'] = $code;
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
            SecurityAuditService::log('otp.failed', 'warn', array_merge(
                SecurityAuditService::fromRequest($request),
                ['identifier' => $request->phone, 'purpose' => $request->purpose]
            ));
            return response()->json(['success' => false, 'message' => 'Invalid or expired OTP'], 422);
        }

        $otp->update(['used_at' => now()]);

        if ($request->purpose === 'register' || $request->purpose === 'login') {
            User::where('phone', $request->phone)->update(['phone_verified_at' => now()]);
        }

        SecurityAuditService::log('otp.verified', 'ok', array_merge(
            SecurityAuditService::fromRequest($request),
            ['identifier' => $request->phone, 'purpose' => $request->purpose]
        ));

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

        $field      = $request->has('email') ? 'email' : 'phone';
        $identifier = $request->$field;
        $ip         = $request->ip();
        $user       = User::where($field, $identifier)->with('role')->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            LoginAttempt::create([
                'identifier'   => $identifier,
                'ip_address'   => $ip,
                'succeeded'    => false,
                'attempted_at' => now(),
            ]);
            SecurityAuditService::log('login.failed', 'warn', array_merge(
                SecurityAuditService::fromRequest($request),
                ['identifier' => $identifier]
            ));
            return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }

        if ($user->status === 'banned') {
            SecurityAuditService::log('login.banned_attempt', 'crit', array_merge(
                SecurityAuditService::fromRequest($request),
                ['user_id' => $user->id, 'identifier' => $identifier]
            ));
            return response()->json(['success' => false, 'message' => 'Your account has been banned'], 403);
        }

        LoginAttempt::create([
            'identifier'   => $identifier,
            'ip_address'   => $ip,
            'succeeded'    => true,
            'attempted_at' => now(),
        ]);

        $deviceName = $this->_deviceName($request);
        $tokenModel = $user->createToken($deviceName);

        DB::table('personal_access_tokens')
            ->where('id', $tokenModel->accessToken->id)
            ->update([
                'ip_address' => $ip,
                'user_agent' => $request->userAgent(),
            ]);

        SecurityAuditService::log('login.success', 'ok', array_merge(
            SecurityAuditService::fromRequest($request),
            ['user_id' => $user->id, 'identifier' => $identifier, 'device' => $deviceName]
        ));

        return response()->json([
            'success' => true,
            'data'    => ['user' => $user, 'token' => $tokenModel->plainTextToken],
        ]);
    }

    public function sessions(Request $request)
    {
        $currentId = $request->user()->currentAccessToken()->id;

        $tokens = DB::table('personal_access_tokens')
            ->where('tokenable_type', 'App\\Models\\User')
            ->where('tokenable_id', $request->user()->id)
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn ($t) => [
                'id'          => $t->id,
                'name'        => $t->name,
                'ip_address'  => $t->ip_address,
                'last_used'   => $t->last_used_at,
                'created_at'  => $t->created_at,
                'is_current'  => $t->id === $currentId,
                'device_type' => $this->_guessDevice($t->user_agent ?? ''),
            ]);

        return response()->json(['success' => true, 'data' => $tokens]);
    }

    public function revokeSession(Request $request, int $id)
    {
        $deleted = DB::table('personal_access_tokens')
            ->where('id', $id)
            ->where('tokenable_type', 'App\\Models\\User')
            ->where('tokenable_id', $request->user()->id)
            ->delete();

        if ($deleted) {
            SecurityAuditService::log('token.revoked', 'info', array_merge(
                SecurityAuditService::fromRequest($request),
                ['user_id' => $request->user()->id, 'token_id' => $id]
            ));
        }

        return response()->json(['success' => true, 'message' => 'Session revoked']);
    }

    public function logout(Request $request)
    {
        SecurityAuditService::log('logout', 'info', array_merge(
            SecurityAuditService::fromRequest($request),
            ['user_id' => $request->user()->id]
        ));
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'Logged out']);
    }

    public function logoutAll(Request $request)
    {
        SecurityAuditService::log('logout.all_devices', 'info', array_merge(
            SecurityAuditService::fromRequest($request),
            ['user_id' => $request->user()->id]
        ));
        $request->user()->tokens()->delete();
        return response()->json(['success' => true, 'message' => 'All devices logged out']);
    }

    public function deactivateAccount(Request $request)
    {
        SecurityAuditService::log('account.deactivated', 'warn', array_merge(
            SecurityAuditService::fromRequest($request),
            ['user_id' => $request->user()->id]
        ));
        $request->user()->update(['status' => 'inactive']);
        $request->user()->tokens()->delete();
        return response()->json(['success' => true, 'message' => 'Account deactivated']);
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
            'password' => 'sometimes|string|min:8|confirmed',
            'language' => 'sometimes|in:so,en,ar',
            'avatar'   => 'sometimes|image|max:2048',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $data = $request->only('name', 'email');
        if ($request->language) $data['preferred_language'] = $request->language;

        if ($request->password) {
            $data['password'] = Hash::make($request->password);
            SecurityAuditService::log('password.changed', 'warn', array_merge(
                SecurityAuditService::fromRequest($request),
                ['user_id' => $user->id]
            ));
        }

        if ($request->hasFile('avatar')) {
            $avatarFile = $request->file('avatar');
            $check      = FileUploadSecurityService::validate($avatarFile, 'avatar');
            if (!$check['ok']) {
                return response()->json(['success' => false, 'message' => $check['reason']], 422);
            }
            $data['avatar'] = $avatarFile->store('avatars', 'public');
        }

        $user->update($data);

        return response()->json(['success' => true, 'data' => $user->fresh()]);
    }

    public function forgotPassword(Request $request)
    {
        $v = Validator::make($request->all(), ['phone' => 'required|string|exists:users,phone']);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $request->merge(['purpose' => 'reset_password']);
        return $this->sendOtp($request);
    }

    public function resetPassword(Request $request)
    {
        $v = Validator::make($request->all(), [
            'phone'    => 'required|string|exists:users,phone',
            'code'     => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
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

        SecurityAuditService::log('password.reset', 'warn', array_merge(
            SecurityAuditService::fromRequest($request),
            ['identifier' => $request->phone]
        ));

        return response()->json(['success' => true, 'message' => 'Password reset successfully']);
    }

    public function deleteAccount(Request $request)
    {
        SecurityAuditService::log('account.deleted', 'warn', array_merge(
            SecurityAuditService::fromRequest($request),
            ['user_id' => $request->user()->id]
        ));
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

    private function _deviceName(Request $request): string
    {
        $ua = $request->userAgent() ?? '';
        if (str_contains($ua, 'Android')) return 'Android';
        if (str_contains($ua, 'iPhone'))  return 'iPhone';
        if (str_contains($ua, 'iPad'))    return 'iPad';
        if (str_contains($ua, 'Windows')) return 'Windows';
        if (str_contains($ua, 'Mac'))     return 'Mac';
        return 'Mobile';
    }

    private function _guessDevice(string $ua): string
    {
        if (str_contains($ua, 'Android')) return 'android';
        if (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) return 'ios';
        if (str_contains($ua, 'Windows')) return 'windows';
        if (str_contains($ua, 'Mac'))     return 'mac';
        return 'mobile';
    }
}
