<?php

namespace App\Http\Controllers\Api\Modules;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ELaundryController extends Controller
{
    // GET /elaundry/items
    public function items()
    {
        $items = DB::table('laundry_items')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'name_so', 'normal_price', 'express_price', 'normal_days', 'express_hours', 'image']);

        return response()->json(['success' => true, 'data' => $items]);
    }

    // POST /elaundry/estimate
    public function estimate(Request $request)
    {
        $v = Validator::make($request->all(), [
            'service_type' => 'required|in:normal,express',
            'items'        => 'required|array|min:1',
            'items.*.id'   => 'required|exists:laundry_items,id',
            'items.*.qty'  => 'required|integer|min:1',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $isExpress   = $request->service_type === 'express';
        $priceField  = $isExpress ? 'express_price' : 'normal_price';
        $itemIds     = array_column($request->items, 'id');
        $dbItems     = DB::table('laundry_items')->whereIn('id', $itemIds)->get()->keyBy('id');

        $lines       = [];
        $total       = 0;

        foreach ($request->items as $reqItem) {
            $dbItem = $dbItems[$reqItem['id']] ?? null;
            if (!$dbItem) continue;
            $price = $dbItem->{$priceField};
            $sub   = $price * $reqItem['qty'];
            $total += $sub;
            $lines[] = [
                'name'     => $dbItem->name,
                'qty'      => $reqItem['qty'],
                'price'    => $price,
                'subtotal' => $sub,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'service_type'  => $request->service_type,
                'delivery_days' => $isExpress ? '24 hours' : '1-2 days',
                'lines'         => $lines,
                'total'         => round($total, 2),
            ],
        ]);
    }

    // POST /elaundry/order (auth)
    public function createOrder(Request $request)
    {
        $v = Validator::make($request->all(), [
            'service_type'       => 'required|in:normal,express',
            'items'              => 'required|array|min:1',
            'items.*.id'         => 'required|exists:laundry_items,id',
            'items.*.qty'        => 'required|integer|min:1',
            'pickup_district_id' => 'required|exists:districts,id',
            'pickup_address'     => 'required|string',
            'delivery_address'   => 'nullable|string',
            'payment_method'     => 'required|in:wallet,cod',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user      = $request->user();
        $isExpress = $request->service_type === 'express';
        $priceField = $isExpress ? 'express_price' : 'normal_price';
        $itemIds   = array_column($request->items, 'id');
        $dbItems   = DB::table('laundry_items')->whereIn('id', $itemIds)->get()->keyBy('id');

        $total = 0;
        $orderLines = [];
        foreach ($request->items as $reqItem) {
            $dbItem = $dbItems[$reqItem['id']] ?? null;
            if (!$dbItem) continue;
            $price = $dbItem->{$priceField};
            $sub   = $price * $reqItem['qty'];
            $total += $sub;
            $orderLines[] = ['name' => $dbItem->name, 'qty' => $reqItem['qty'], 'price' => $price, 'sub' => $sub];
        }

        if ($request->payment_method === 'wallet') {
            if (!$user->wallet || $user->wallet->balance < $total) {
                return response()->json(['success' => false, 'message' => 'Insufficient wallet balance'], 422);
            }
        }

        $district = DB::table('districts')->find($request->pickup_district_id);

        $order = DB::transaction(function () use ($request, $user, $total, $orderLines, $district, $isExpress) {
            $order = Order::create([
                'order_number'    => 'LDR-' . strtoupper(Str::random(8)),
                'user_id'         => $user->id,
                'module_slug'     => 'elaundry',
                'status'          => 'pending',
                'payment_method'  => $request->payment_method,
                'payment_status'  => $request->payment_method === 'wallet' ? 'paid' : 'unpaid',
                'delivery_address'=> ['address' => $request->delivery_address ?? $request->pickup_address, 'district' => $district?->name],
                'subtotal'        => $total,
                'delivery_fee'    => 0,
                'total_amount'    => $total,
                'note'            => json_encode([
                    'service_type'   => $request->service_type,
                    'items'          => $orderLines,
                    'pickup_address' => $request->pickup_address,
                    'district'       => $district?->name,
                    'eta'            => $isExpress ? '24 hours' : '1-2 days',
                ]),
                'placed_at' => now(),
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id, 'status' => 'pending',
                'note' => 'Laundry order placed', 'actor_id' => $user->id,
                'actor_type' => 'App\\Models\\User',
            ]);

            if ($request->payment_method === 'wallet') {
                $user->wallet->decrement('balance', $total);
            }

            return $order;
        });

        return response()->json([
            'success' => true,
            'message' => 'Laundry order placed!',
            'data'    => ['order_number' => $order->order_number, 'total' => $total],
        ], 201);
    }
}
