<?php

namespace App\Http\Controllers\Api\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalUser;
use App\Models\Global\GlobalAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GlobalAuthController extends Controller
{
    // Sanctum guard name for global users
    private const GUARD = 'global_users';

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'email'           => 'required|email|unique:global_users,email',
            'password'        => 'required|string|min:8|confirmed',
            'phone'           => 'nullable|string|max:20',
            'address_line1'   => 'required|string|max:255',
            'address_line2'   => 'nullable|string|max:255',
            'city'            => 'required|string|max:100',
            'state'           => 'nullable|string|max:100',
            'zip'             => 'nullable|string|max:20',
            'country'         => 'required|string|max:2',
            'fcm_token'       => 'nullable|string',
        ]);

        $user = GlobalUser::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'phone'     => $data['phone'] ?? null,
            'country'   => $data['country'],
            'fcm_token' => $data['fcm_token'] ?? null,
        ]);

        // Save default address
        GlobalAddress::create([
            'global_user_id' => $user->id,
            'name'           => $data['name'],
            'label'          => 'Home',
            'phone'          => $data['phone'] ?? null,
            'address_line1'  => $data['address_line1'],
            'address_line2'  => $data['address_line2'] ?? null,
            'city'           => $data['city'],
            'state'          => $data['state'] ?? null,
            'zip_code'       => $data['zip'],
            'zip'            => $data['zip'],
            'country_code'   => $data['country'],
            'country'        => $data['country'],
            'is_default'     => true,
        ]);

        $token = $user->createToken('global-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->userResource($user->load('addresses')),
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'     => 'required|email',
            'password'  => 'required|string',
            'fcm_token' => 'nullable|string',
        ]);

        $user = GlobalUser::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }

        if ($user->is_banned) {
            return response()->json(['message' => 'Account suspended. Contact support.'], 403);
        }

        // Update FCM token if provided
        if (!empty($data['fcm_token'])) {
            $user->update(['fcm_token' => $data['fcm_token']]);
        }

        $token = $user->createToken('global-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->userResource($user->load('addresses')),
        ]);
    }

    /**
     * POST /global/auth/google
     * Verify Google ID token → find or create GlobalUser → return Sanctum token.
     */
    public function googleAuth(Request $request)
    {
        $request->validate([
            'id_token'  => 'required|string',
            'fcm_token' => 'nullable|string',
        ]);

        // Verify ID token with Google's tokeninfo endpoint
        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $request->id_token,
        ]);

        if (!$response->successful()) {
            Log::warning('[GlobalAuth] Google token verification failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return response()->json(['message' => 'Invalid Google token. Please try again.'], 401);
        }

        $google = $response->json();

        if (empty($google['email'])) {
            return response()->json(['message' => 'Google account has no email.'], 422);
        }

        // Find or create user
        $user = GlobalUser::firstOrCreate(
            ['email' => $google['email']],
            [
                'name'      => $google['name'] ?? explode('@', $google['email'])[0],
                'avatar'    => $google['picture'] ?? null,
                'password'  => Hash::make(Str::random(32)),
                'is_active' => true,
                'email_verified' => true,
                'country'   => 'US',
            ]
        );

        if ($user->is_banned) {
            return response()->json(['message' => 'Account suspended. Contact support.'], 403);
        }

        // Update avatar and name if Google provides newer data
        $updates = [];
        if (!empty($google['picture']) && $user->avatar !== $google['picture']) {
            $updates['avatar'] = $google['picture'];
        }
        if (!empty($google['name']) && $user->name !== $google['name'] && str_starts_with($user->name, explode('@', $google['email'])[0])) {
            $updates['name'] = $google['name'];
        }
        if (!empty($request->fcm_token)) {
            $updates['fcm_token'] = $request->fcm_token;
        }
        if (!empty($updates)) {
            $user->update($updates);
        }

        $token = $user->createToken('global-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->userResource($user->load('addresses')),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user(self::GUARD)->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $this->userResource($request->user(self::GUARD)->load('addresses')),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user(self::GUARD);
        $data = $request->validate([
            'name'      => 'sometimes|string|max:100',
            'phone'     => 'sometimes|nullable|string|max:20',
            'avatar'    => 'sometimes|nullable|url',
            'fcm_token' => 'sometimes|nullable|string',
        ]);
        $user->update($data);
        return response()->json(['user' => $this->userResource($user)]);
    }

    public function addAddress(Request $request)
    {
        $user = $request->user(self::GUARD);
        $data = $request->validate([
            'name'          => 'required|string',
            'phone'         => 'nullable|string',
            'address_line1' => 'required|string',
            'address_line2' => 'nullable|string',
            'city'          => 'required|string',
            'state'         => 'nullable|string',
            'zip'           => 'nullable|string|max:20',
            'country'       => 'required|string|max:2',
            'is_default'    => 'boolean',
        ]);

        if ($request->boolean('is_default')) {
            GlobalAddress::where('global_user_id', $user->id)->update(['is_default' => false]);
        }

        $address = GlobalAddress::create([
            'global_user_id' => $user->id,
            'address_line1'  => $data['address_line1'],
            'address_line2'  => $data['address_line2'] ?? null,
            'city'           => $data['city'],
            'state'          => $data['state'] ?? null,
            'zip_code'       => $data['zip'],
            'zip'            => $data['zip'],
            'country_code'   => $data['country'],
            'country'        => $data['country'],
            'name'           => $data['name'],
            'phone'          => $data['phone'] ?? null,
            'is_default'     => $request->boolean('is_default', false),
        ]);

        return response()->json(['address' => $address], 201);
    }

    public function updateAddress(Request $request, $addressId)
    {
        $user    = $request->user(self::GUARD);
        $address = GlobalAddress::where('id', $addressId)
            ->where('global_user_id', $user->id)
            ->firstOrFail();

        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'phone'         => 'nullable|string|max:20',
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city'          => 'required|string|max:100',
            'state'         => 'nullable|string|max:100',
            'zip'           => 'nullable|string|max:20',
            'country'       => 'required|string|max:2',
        ]);

        $address->update([
            'name'          => $data['name'],
            'phone'         => $data['phone'] ?? null,
            'address_line1' => $data['address_line1'],
            'address_line2' => $data['address_line2'] ?? null,
            'city'          => $data['city'],
            'state'         => $data['state'] ?? null,
            'zip_code'      => $data['zip'] ?? null,
            'zip'           => $data['zip'] ?? null,
            'country_code'  => $data['country'],
            'country'       => $data['country'],
        ]);

        // Reload user so Flutter gets updated addresses
        $user->load('addresses');
        return response()->json(['user' => $this->userResource($user)]);
    }

    private function userResource(GlobalUser $user): array
    {
        return [
            'id'        => $user->id,
            'name'      => $user->name,
            'email'     => $user->email,
            'phone'     => $user->phone,
            'avatar'    => $user->avatar,
            'country'   => $user->country,
            'addresses' => $user->relationLoaded('addresses') ? $user->addresses : [],
        ];
    }
}
