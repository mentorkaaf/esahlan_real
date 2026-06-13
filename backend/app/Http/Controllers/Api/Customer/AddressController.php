<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\UserAddress;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $addresses = UserAddress::with('district')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->get();

        return response()->json(['success' => true, 'data' => $addresses]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label'       => 'required|string|max:50',
            'address'     => 'required|string',
            'latitude'    => 'required|numeric',
            'longitude'   => 'required|numeric',
            'district_id' => 'nullable|exists:districts,id',
            'is_default'  => 'boolean',
        ]);

        $data['user_id'] = $request->user()->id;

        if (!empty($data['is_default'])) {
            UserAddress::where('user_id', $request->user()->id)->update(['is_default' => false]);
        }

        $address = UserAddress::create($data);

        return response()->json(['success' => true, 'data' => $address, 'message' => 'Address saved'], 201);
    }

    public function update(Request $request, UserAddress $address)
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $data = $request->validate([
            'label'       => 'sometimes|string|max:50',
            'address'     => 'sometimes|string',
            'latitude'    => 'sometimes|numeric',
            'longitude'   => 'sometimes|numeric',
            'district_id' => 'nullable|exists:districts,id',
            'is_default'  => 'boolean',
        ]);

        if (!empty($data['is_default'])) {
            UserAddress::where('user_id', $request->user()->id)->update(['is_default' => false]);
        }

        $address->update($data);

        return response()->json(['success' => true, 'data' => $address, 'message' => 'Address updated']);
    }

    public function destroy(Request $request, UserAddress $address)
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        $address->delete();

        return response()->json(['success' => true, 'message' => 'Address deleted']);
    }

    public function setDefault(Request $request, UserAddress $address)
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        UserAddress::where('user_id', $request->user()->id)->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return response()->json(['success' => true, 'message' => 'Default address updated']);
    }
}
