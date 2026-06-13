<?php

namespace App\Http\Controllers\Api\Modules;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EMovingController extends Controller
{
    // GET /emoving/move-types
    public function moveTypes()
    {
        $types = [
            ['id' => 'house',       'name' => 'House Moving',      'icon' => 'home',         'color' => '#2ECC71', 'description' => 'Move your home furniture & belongings'],
            ['id' => 'office',      'name' => 'Office Moving',     'icon' => 'business',     'color' => '#3498DB', 'description' => 'Professional office relocation'],
            ['id' => 'commercial',  'name' => 'Commercial',        'icon' => 'store',        'color' => '#9B59B6', 'description' => 'Large commercial goods moving'],
            ['id' => 'single_item', 'name' => 'Single Item',       'icon' => 'inventory_2',  'color' => '#E67E22', 'description' => 'Move one item or a few pieces'],
        ];
        return response()->json(['success' => true, 'data' => $types]);
    }

    // GET /emoving/districts
    public function districts()
    {
        $districts = DB::table('districts')
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'name_so']);
        return response()->json(['success' => true, 'data' => $districts]);
    }

    // GET /emoving/extra-services
    public function extraServices()
    {
        $services = DB::table('moving_extra_services')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'name_so', 'price', 'unit']);
        return response()->json(['success' => true, 'data' => $services]);
    }

    // GET /emoving/packages/{type} — packages for any move type
    public function packages($type)
    {
        if (!in_array($type, ['commercial', 'office', 'house', 'single_item'])) {
            return response()->json(['success' => false, 'message' => 'Invalid type'], 422);
        }
        $packages = DB::table('moving_packages')
            ->where('move_type', $type)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $result = $packages->map(function ($p) {
            $includes = is_string($p->includes) ? (json_decode($p->includes, true) ?? []) : ($p->includes ?? []);
            return [
                'id'          => $p->id,
                'name'        => $p->name,
                'description' => $p->description,
                'price'       => (float)$p->price,
                'includes'    => $includes,
            ];
        });

        return response()->json(['success' => true, 'data' => $result]);
    }

    // POST /emoving/calculate
    public function calculate(Request $request)
    {
        $v = Validator::make($request->all(), [
            'from_district_id' => 'required|exists:districts,id',
            'to_district_id'   => 'required|exists:districts,id',
            'move_type'        => 'required|in:house,office,commercial,single_item',
            'room_count'       => 'nullable|integer|min:1',
            'package_id'       => 'nullable|integer',
            'extra_services'   => 'nullable|array',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        // Get route pricing
        $pricing = DB::table('moving_pricing')
            ->where('from_district_id', $request->from_district_id)
            ->where('to_district_id',   $request->to_district_id)
            ->where('move_type',        $request->move_type)
            ->where('is_active', true)
            ->first();

        $defaults  = ['house' => 60, 'office' => 80, 'commercial' => 120, 'single_item' => 25];
        $basePrice = (float)($pricing?->base_price ?? $defaults[$request->move_type] ?? 60);
        $pricePerRoom = (float)($pricing?->price_per_room ?? 10);

        $breakdown = [
            'base_price'  => $basePrice,
            'room_price'  => 0,
            'package_price'=> 0,
            'extra_fee'   => 0,
            'distance_fee'=> 0,
        ];

        // House: room-based pricing
        if ($request->move_type === 'house' && $request->room_count) {
            $breakdown['room_price'] = $request->room_count * $pricePerRoom;
        }

        // Commercial/Office: package pricing
        if (in_array($request->move_type, ['commercial', 'office']) && !empty($request->package_id)) {
            $pkg = DB::table('moving_packages')->find($request->package_id);
            if ($pkg) {
                $breakdown['package_price'] = (float)$pkg->price;
                $basePrice = 0; // Package replaces base for commercial/office
                $breakdown['base_price'] = 0;
            }
        }

        // Extra services
        if (!empty($request->extra_services)) {
            $breakdown['extra_fee'] = (float)DB::table('moving_extra_services')
                ->whereIn('id', $request->extra_services)->sum('price');
        }

        // Distance fee: 0 if same district, flat fee otherwise
        $breakdown['distance_fee'] = ($request->from_district_id == $request->to_district_id) ? 0 : 20.00;

        $total = round(
            $basePrice +
            $breakdown['room_price'] +
            $breakdown['package_price'] +
            $breakdown['extra_fee'] +
            $breakdown['distance_fee'],
            2
        );

        return response()->json([
            'success' => true,
            'data'    => array_merge($breakdown, ['total' => $total, 'currency' => 'USD']),
        ]);
    }

    // POST /emoving/order (auth)
    public function createOrder(Request $request)
    {
        $v = Validator::make($request->all(), [
            'from_district_id' => 'required|exists:districts,id',
            'to_district_id'   => 'required|exists:districts,id',
            'pickup_address'   => 'nullable|string',
            'delivery_address' => 'nullable|string',
            'move_type'        => 'required|in:house,office,commercial,single_item',
            'room_count'       => 'nullable|integer|min:1',
            'package_id'       => 'nullable|integer',
            'extra_services'   => 'nullable|array',
            'scheduled_date'   => 'required|date|after_or_equal:today',
            'payment_method'   => 'required|in:wallet,cod',
            'note'             => 'nullable|string',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user = $request->user();
        $calcResp = $this->calculate($request);
        $calc  = json_decode($calcResp->getContent(), true)['data'];
        $total = $calc['total'];

        if ($request->payment_method === 'wallet') {
            if (!$user->wallet || $user->wallet->balance < $total) {
                return response()->json(['success' => false, 'message' => 'Insufficient wallet balance'], 422);
            }
        }

        $extraNames = !empty($request->extra_services)
            ? DB::table('moving_extra_services')->whereIn('id', $request->extra_services)->pluck('name')->toArray()
            : [];
        $packageName = null;
        if (!empty($request->package_id)) {
            $packageName = DB::table('moving_packages')->find($request->package_id)?->name;
        }
        $fromDistrict = DB::table('districts')->find($request->from_district_id)?->name;
        $toDistrict   = DB::table('districts')->find($request->to_district_id)?->name;

        $order = DB::transaction(function () use ($request, $user, $total, $calc, $extraNames, $packageName, $fromDistrict, $toDistrict) {
            $order = Order::create([
                'order_number'    => 'MOV-' . strtoupper(Str::random(8)),
                'user_id'         => $user->id,
                'module_slug'     => 'emoving',
                'status'          => 'pending',
                'payment_method'  => $request->payment_method,
                'payment_status'  => $request->payment_method === 'wallet' ? 'paid' : 'pending',
                'delivery_address'=> ['from' => $request->pickup_address, 'to' => $request->delivery_address],
                'subtotal'        => $total,
                'delivery_fee'    => 0,
                'total_amount'    => $total,
                'note'            => json_encode(array_merge($calc, [
                    'move_type'      => $request->move_type,
                    'room_count'     => $request->room_count,
                    'package'        => $packageName,
                    'extra_services' => $extraNames,
                    'from_district'  => $fromDistrict,
                    'to_district'    => $toDistrict,
                    'pickup_address' => $request->pickup_address,
                    'delivery_address'=> $request->delivery_address,
                    'scheduled_date' => $request->scheduled_date,
                    'customer_name'  => $user->name,
                    'customer_phone' => $user->phone,
                    'user_note'      => $request->note,
                ])),
                'placed_at' => now(),
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id, 'status' => 'pending',
                'note' => 'Moving order placed', 'actor_id' => $user->id,
                'actor_type' => 'App\\Models\\User',
            ]);

            if ($request->payment_method === 'wallet') {
                $user->wallet->decrement('balance', $total);
                DB::table('wallet_transactions')->insert([
                    'wallet_id'   => $user->wallet->id,
                    'type'        => 'debit',
                    'amount'      => $total,
                    'description' => "eMoving: {$fromDistrict} → {$toDistrict}",
                    'reference_id'=> $order->id,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
            return $order;
        });

        return response()->json([
            'success' => true,
            'message' => 'Moving service booked! 🚚',
            'data'    => [
                'order_number'   => $order->order_number,
                'total'          => $total,
                'scheduled_date' => $request->scheduled_date,
                'from'           => $fromDistrict,
                'to'             => $toDistrict,
            ],
        ], 201);
    }

    // GET /emoving/my-orders (auth)
    public function myOrders(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->where('module_slug', 'emoving')
            ->orderByDesc('placed_at')
            ->get()
            ->map(function ($order) {
                $note = is_string($order->note) ? (json_decode($order->note, true) ?? []) : ($order->note ?? []);
                return [
                    'id'             => $order->id,
                    'order_number'   => $order->order_number,
                    'status'         => $order->status,
                    'payment_method' => $order->payment_method,
                    'payment_status' => $order->payment_status,
                    'total'          => (float) $order->total_amount,
                    'move_type'      => $note['move_type'] ?? null,
                    'from_district'  => $note['from_district'] ?? null,
                    'to_district'    => $note['to_district'] ?? null,
                    'scheduled_date' => $note['scheduled_date'] ?? null,
                    'package'        => $note['package'] ?? null,
                    'room_count'     => $note['room_count'] ?? null,
                    'extra_services' => $note['extra_services'] ?? [],
                    'placed_at'      => $order->placed_at,
                ];
            });

        return response()->json(['success' => true, 'data' => $orders]);
    }
}
