<?php

namespace App\Http\Controllers\Api\Modules;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Services\LoyaltyService;

class EParcelController extends Controller
{
    public function types()
    {
        $types = DB::table('parcel_types')->where('is_active', true)->get();
        return response()->json(['success' => true, 'data' => $types]);
    }

    // GET /eparcel/districts
    public function districts()
    {
        $districts = DB::table('districts')->where('status', 'active')
            ->orderBy('sort_order')->get(['id', 'name', 'name_so']);
        return response()->json(['success' => true, 'data' => $districts]);
    }

    // GET /eparcel/zones — price matrix for all district pairs
    public function zones()
    {
        $module = DB::table('modules')->where('slug', 'eparcel')->first();
        if (!$module) return response()->json(['success' => true, 'data' => []]);

        $zones = DB::table('delivery_zone_pricing')
            ->where('module_id', $module->id)
            ->where('is_active', true)
            ->join('districts as fd', 'delivery_zone_pricing.from_district_id', '=', 'fd.id')
            ->join('districts as td', 'delivery_zone_pricing.to_district_id',   '=', 'td.id')
            ->select([
                'delivery_zone_pricing.from_district_id',
                'delivery_zone_pricing.to_district_id',
                'delivery_zone_pricing.base_price',
                'fd.name as from_district',
                'td.name as to_district',
            ])
            ->get();

        return response()->json(['success' => true, 'data' => $zones]);
    }

    public function calculate(Request $request)
    {
        $v = Validator::make($request->all(), [
            'parcel_type_id'       => 'required|exists:parcel_types,id',
            'pickup_district_id'   => 'required|exists:districts,id',
            'delivery_district_id' => 'required|exists:districts,id',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        // Zone pricing: district-to-district flat price
        $zone = DB::table('delivery_zone_pricing')
            ->where('module_id',        'eparcel')
            ->where('from_district_id', $request->pickup_district_id)
            ->where('to_district_id',   $request->delivery_district_id)
            ->where('is_active', true)
            ->first();

        if (!$zone) {
            return response()->json([
                'success' => false,
                'message' => 'No pricing available for this route. Please contact support.',
            ], 422);
        }

        $fromDistrict = DB::table('districts')->find($request->pickup_district_id);
        $toDistrict   = DB::table('districts')->find($request->delivery_district_id);

        return response()->json([
            'success' => true,
            'data'    => [
                'total'         => round($zone->base_price, 2),
                'from_district' => $fromDistrict?->name,
                'to_district'   => $toDistrict?->name,
                'currency'      => 'USD',
            ],
        ]);
    }

    public function createOrder(Request $request)
    {
        $v = Validator::make($request->all(), [
            'parcel_type_id'               => 'required|exists:parcel_types,id',
            'pickup_address'               => 'required|array',
            'pickup_address.district_id'   => 'required|exists:districts,id',
            'delivery_address'             => 'required|array',
            'delivery_address.district_id' => 'required|exists:districts,id',
            'recipient_name'               => 'required|string',
            'recipient_phone'              => 'required|string',
            'description'                  => 'nullable|string',
            'payment_method'               => 'required|in:wallet,waafi_pay,mobile_pay',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user = $request->user();

        // Zone-based flat pricing
        $zone = DB::table('delivery_zone_pricing')
            ->where('module_id',        'eparcel')
            ->where('from_district_id', $request->input('pickup_address.district_id'))
            ->where('to_district_id',   $request->input('delivery_address.district_id'))
            ->where('is_active', true)
            ->first();

        if (!$zone) {
            return response()->json([
                'success' => false,
                'message' => 'No pricing available for this route.',
            ], 422);
        }

        $totalAmount = round($zone->base_price, 2);

        // Points redeem
        $loyalty     = LoyaltyService::processOrderRequest($request, $user->id, $totalAmount, 'eparcel');
        $totalAmount = round(max(0, $totalAmount - $loyalty['points_discount']), 2);

        if ($request->payment_method === 'wallet') {
            $wallet = Wallet::getOrCreateFor('App\\Models\\User', $user->id);
            if ($wallet->balance < $totalAmount) {
                return response()->json(['success' => false, 'message' => 'Insufficient wallet balance'], 422);
            }
        }

        $order = DB::transaction(function () use ($request, $user, $totalAmount, $loyalty) {
            $order = Order::create([
                'order_number'    => 'PCL-' . strtoupper(Str::random(8)),
                'user_id'         => $user->id,
                'module_slug'     => 'eparcel',
                'status'          => 'pending',
                'payment_method'  => $request->payment_method,
                'payment_status'  => $request->payment_method === 'wallet' ? 'paid' : 'unpaid',
                'delivery_address'=> $request->delivery_address,
                'subtotal'        => $totalAmount,
                'delivery_fee'    => 0,
                'total_amount'    => $totalAmount,
                'points_used'     => $loyalty['points_used'],
                'points_discount' => $loyalty['points_discount'],
                'note'            => json_encode([
                    'pickup'          => $request->pickup_address,
                    'recipient'       => $request->recipient_name,
                    'recipient_phone' => $request->recipient_phone,
                    'description'     => $request->description,
                ]),
                'placed_at'       => now(),
            ]);

            DB::table('order_status_history')->insert([
                'order_id'   => $order->id,
                'status'     => 'pending',
                'note'       => 'Parcel order placed',
                'actor_id'   => $user->id,
                'actor_type' => 'App\\Models\\User',
                'changed_by' => $user->id,
                'created_at' => now(),
            ]);

            if ($request->payment_method === 'wallet') {
                $w = Wallet::getOrCreateFor('App\\Models\\User', $user->id);
                $w->debit($totalAmount, "eParcel: {$request->recipient_name}", 'App\\Models\\Order', $order->id);
            }

            return $order;
        });

        // ── Push notification: order placed ──────────────────────────────
        try {
            if (!empty($user->fcm_token)) {
                \App\Services\FcmService::sendOrderUpdate(
                    $user->fcm_token,
                    $order->order_number,
                    'pending',
                    $order->id,
                    'eparcel',
                );
            }
        } catch (\Throwable) {}
        return response()->json([
            'success' => true,
            'message' => 'Parcel order created',
            'data'    => $order,
        ], 201);
    }
}
