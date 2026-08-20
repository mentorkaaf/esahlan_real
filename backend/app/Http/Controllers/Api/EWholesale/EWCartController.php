<?php

namespace App\Http\Controllers\Api\EWholesale;

use App\Http\Controllers\Controller;
use App\Models\EWholesale\EWBuyer;
use App\Services\EWholesale\CartValidator;
use Illuminate\Http\Request;

class EWCartController extends Controller
{
    public function __construct(private CartValidator $validator) {}

    // ── POST /api/v1/ewholesale/cart/validate ─────────────────────────────
    //
    // Request body:
    //   lines: [{product_id, variant_id?, qty}]
    //   district_id?: int   (for shipping zone calculation)
    //
    // Response:
    //   groups: [{supplier_id, supplier_name, lines (re-priced), subtotal,
    //             delivery_fee, platform_fee, total,
    //             available_payment_plans, min_deposit_percent}]
    //   errors:   [{line, code, message, ...}]
    //   warnings: [{line, code, message, ...}]

    public function validate(Request $r)
    {
        $r->validate([
            'lines'             => 'required|array|min:1|max:50',
            'lines.*.product_id'=> 'required|integer',
            'lines.*.variant_id'=> 'nullable|integer',
            'lines.*.qty'       => 'required|numeric|min:0.01',
            'district_id'       => 'nullable|integer',
        ]);

        // Buyer is optional (public browsing shows prices without credit plan)
        $buyer = null;
        if ($r->user()) {
            $buyer = EWBuyer::where('user_id', $r->user()->id)->first();
        }

        $result = $this->validator->validate(
            lines:      $r->lines,
            buyer:      $buyer,
            districtId: $r->district_id,
        );

        $statusCode = empty($result['errors']) ? 200 : 422;

        return response()->json([
            'data'     => [
                'groups'   => $result['groups'],
                'errors'   => $result['errors'],
                'warnings' => $result['warnings'],
            ],
        ], $statusCode);
    }
}
