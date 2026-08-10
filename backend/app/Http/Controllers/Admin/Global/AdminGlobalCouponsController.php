<?php

namespace App\Http\Controllers\Admin\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalCoupon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminGlobalCouponsController extends Controller
{
    public function index()
    {
        $coupons = GlobalCoupon::withCount('userCoupons')->latest()->get();
        return view('admin.global.coupons.index', compact('coupons'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:80',
            'description'      => 'nullable|string|max:200',
            'label'            => 'nullable|string|max:30',
            'type'             => 'required|in:percentage,fixed',
            'value'            => 'required|numeric|min:0.01',
            'minimum_order'    => 'nullable|numeric|min:0',
            'maximum_discount' => 'nullable|numeric|min:0',
            'usage_limit'      => 'nullable|integer|min:1',
            'per_user_limit'   => 'nullable|integer|min:1',
            'is_new_user_only' => 'nullable|boolean',
            'is_active'        => 'nullable|boolean',
            'starts_at'        => 'nullable|date',
            'expires_at'       => 'nullable|date',
        ]);

        $data['code']            = strtoupper(Str::random(8));
        $data['is_active']       = $request->boolean('is_active', true);
        $data['is_new_user_only'] = $request->boolean('is_new_user_only', false);
        $data['minimum_order']   = $data['minimum_order'] ?? 0;
        $data['per_user_limit']  = $data['per_user_limit'] ?? 1;
        $data['label']           = $data['label'] ?? ($data['is_new_user_only'] ? 'New User' : 'Deal');

        GlobalCoupon::create($data);
        return back()->with('success', 'Coupon created.');
    }

    public function update(Request $request, GlobalCoupon $coupon)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:80',
            'description'      => 'nullable|string|max:200',
            'label'            => 'nullable|string|max:30',
            'type'             => 'required|in:percentage,fixed',
            'value'            => 'required|numeric|min:0.01',
            'minimum_order'    => 'nullable|numeric|min:0',
            'maximum_discount' => 'nullable|numeric|min:0',
            'usage_limit'      => 'nullable|integer|min:1',
            'per_user_limit'   => 'nullable|integer|min:1',
            'is_new_user_only' => 'nullable|boolean',
            'is_active'        => 'nullable|boolean',
            'starts_at'        => 'nullable|date',
            'expires_at'       => 'nullable|date',
        ]);

        $data['is_active']        = $request->boolean('is_active', false);
        $data['is_new_user_only'] = $request->boolean('is_new_user_only', false);
        $data['minimum_order']    = $data['minimum_order'] ?? 0;

        $coupon->update($data);
        return back()->with('success', 'Coupon updated.');
    }

    public function destroy(GlobalCoupon $coupon)
    {
        $coupon->delete();
        return back()->with('success', 'Coupon deleted.');
    }

    public function toggle(GlobalCoupon $coupon)
    {
        $coupon->update(['is_active' => !$coupon->is_active]);
        return back()->with('success', $coupon->is_active ? 'Coupon activated.' : 'Coupon deactivated.');
    }
}
