<?php

namespace App\Http\Controllers\Api\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalUser;
use App\Models\Global\GlobalAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class GlobalAuthController extends Controller
{
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
            'zip'             => 'required|string|max:20',
            'country'         => 'required|string|size:2',
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

        // Save default address — use actual DB column names
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
            'user'  => $this->userResource($user),
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

        if ($data['fcm_token']) {
            $user->update(['fcm_token' => $data['fcm_token']]);
        }

        $token = $user->createToken('global-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->userResource($user->load('addresses')),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user('global')->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $this->userResource($request->user('global')->load('addresses')),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user('global');
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
        $user = $request->user('global');
        $data = $request->validate([
            'name'          => 'required|string',
            'phone'         => 'nullable|string',
            'address_line1' => 'required|string',
            'address_line2' => 'nullable|string',
            'city'          => 'required|string',
            'state'         => 'nullable|string',
            'zip'           => 'required|string',
            'country'       => 'required|string|size:2',
            'is_default'    => 'boolean',
        ]);

        if ($request->boolean('is_default')) {
            GlobalAddress::where('global_user_id', $user->id)->update(['is_default' => false]);
        }

        $address = GlobalAddress::create(['global_user_id' => $user->id] + $data);

        return response()->json(['address' => $address], 201);
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
            'addresses' => $user->addresses ?? [],
        ];
    }
}
