<?php

namespace App\Http\Controllers\Api\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalCoupon;
use App\Models\Global\GlobalUserCoupon;
use Illuminate\Http\Request;

class GlobalCouponsController extends Controller
{
    /**
     * GET /coupons/available
     * Returns coupons the user can collect (new-user deals popup).
     * Called on home screen — shows popup if uncollected deals exist.
     */
    public function available(Request $request)
    {
        $user    = $request->user('global_users');
        $userId  = $user?->id;

        // Collected coupon IDs for this user
        $collected = $userId
            ? GlobalUserCoupon::where('global_user_id', $userId)->pluck('global_coupon_id')->toArray()
            : [];

        $coupons = GlobalCoupon::where('is_active', true)
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->where(fn($q) => $q->whereNull('usage_limit')->orWhereRaw('used_count < usage_limit'))
            ->get()
            ->filter(function ($c) use ($userId, $collected) {
                // Skip already collected
                if (in_array($c->id, $collected)) return false;
                return true;
            })
            ->map(fn($c) => $this->formatCoupon($c))
            ->values();

        return response()->json(['coupons' => $coupons]);
    }

    /**
     * POST /coupons/collect-all
     * Collect all available coupons at once (Shein-style "Collect All" button).
     */
    public function collectAll(Request $request)
    {
        $user = $request->user('global_users');

        $collected = GlobalUserCoupon::where('global_user_id', $user->id)->pluck('global_coupon_id')->toArray();

        $coupons = GlobalCoupon::where('is_active', true)
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->where(fn($q) => $q->whereNull('usage_limit')->orWhereRaw('used_count < usage_limit'))
            ->whereNotIn('id', $collected)
            ->get();

        $now = now();
        foreach ($coupons as $coupon) {
            GlobalUserCoupon::firstOrCreate(
                ['global_user_id' => $user->id, 'global_coupon_id' => $coupon->id],
                ['collected_at' => $now]
            );
        }

        return response()->json(['collected' => $coupons->count(), 'message' => 'Coupons added to your wallet!']);
    }

    /**
     * POST /coupons/{id}/collect
     * Collect a single coupon.
     */
    public function collect(Request $request, GlobalCoupon $coupon)
    {
        $user = $request->user('global_users');

        if (!$coupon->isValid()) {
            return response()->json(['message' => 'Coupon is no longer available.'], 422);
        }

        $existing = GlobalUserCoupon::where('global_user_id', $user->id)
            ->where('global_coupon_id', $coupon->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Already collected.'], 422);
        }

        GlobalUserCoupon::create([
            'global_user_id'   => $user->id,
            'global_coupon_id' => $coupon->id,
            'collected_at'     => now(),
        ]);

        return response()->json(['message' => 'Coupon collected!', 'coupon' => $this->formatCoupon($coupon)]);
    }

    /**
     * GET /coupons/my
     * List user's collected (unused) coupons for checkout selector.
     */
    public function myCoupons(Request $request)
    {
        $user = $request->user('global_users');

        $userCoupons = GlobalUserCoupon::with('coupon')
            ->where('global_user_id', $user->id)
            ->where('is_used', false)
            ->get()
            ->filter(fn($uc) => $uc->coupon && $uc->coupon->isValid())
            ->map(fn($uc) => $this->formatCoupon($uc->coupon))
            ->values();

        return response()->json(['coupons' => $userCoupons]);
    }

    /**
     * POST /coupons/validate
     * Validate a coupon code at checkout and return discount amount.
     */
    public function validate(Request $request)
    {
        $request->validate([
            'code'        => 'required|string',
            'order_total' => 'required|numeric|min:0',
        ]);

        $coupon = GlobalCoupon::where('code', strtoupper($request->code))->first();

        if (!$coupon || !$coupon->isValid()) {
            return response()->json(['valid' => false, 'message' => 'Invalid or expired coupon.'], 422);
        }

        $user = $request->user('global_users');
        if ($user) {
            // Check per-user limit
            $usedCount = GlobalUserCoupon::where('global_user_id', $user->id)
                ->where('global_coupon_id', $coupon->id)
                ->where('is_used', true)
                ->count();
            if ($usedCount >= $coupon->per_user_limit) {
                return response()->json(['valid' => false, 'message' => 'You have already used this coupon.'], 422);
            }
        }

        $discount = $coupon->calculateDiscount((float) $request->order_total);

        if ($discount <= 0) {
            return response()->json(['valid' => false, 'message' => "Minimum order is \${$coupon->minimum_order}."], 422);
        }

        return response()->json([
            'valid'       => true,
            'coupon_id'   => $coupon->id,
            'code'        => $coupon->code,
            'name'        => $coupon->name,
            'discount'    => $discount,
            'type'        => $coupon->type,
            'value'       => $coupon->value,
        ]);
    }

    private function formatCoupon(GlobalCoupon $c): array
    {
        return [
            'id'               => $c->id,
            'name'             => $c->name,
            'description'      => $c->description,
            'label'            => $c->label,
            'code'             => $c->code,
            'type'             => $c->type,
            'value'            => $c->value,
            'minimum_order'    => $c->minimum_order,
            'maximum_discount' => $c->maximum_discount,
            'is_new_user_only' => $c->is_new_user_only,
            'expires_at'       => $c->expires_at?->toDateTimeString(),
        ];
    }
}
