<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Http\Controllers\Controller;
use App\Events\DeliveryLocationUpdated;
use App\Models\Deliveryman;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\OrderTracking;
use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DeliveryController extends Controller
{
    private function deliveryman(Request $request): ?Deliveryman
    {
        return Deliveryman::where('user_id', $request->user()->id)->first();
    }

    public function register(Request $request)
    {
        $v = Validator::make($request->all(), [
            'name'         => 'required|string|max:100',
            'phone'        => 'required|string|unique:users,phone',
            'password'     => 'required|string|min:6',
            'vehicle_type' => 'required|in:motorcycle,car,bicycle,truck',
            'plate_number' => 'nullable|string',
        ]);

        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $role = DB::table('roles')->where('slug', 'deliveryman')->first();

        $user = DB::transaction(function () use ($request, $role) {
            $user = User::create([
                'uuid'         => (string) Str::uuid(),
                'name'         => $request->name,
                'phone'        => $request->phone,
                'password'     => Hash::make($request->password),
                'role_id'      => $role?->id,
                'status'       => 'active',
                'referral_code'=> strtoupper(Str::random(8)),
            ]);

            Deliveryman::create([
                'user_id'      => $user->id,
                'vehicle_type' => $request->vehicle_type,
                'plate_number' => $request->plate_number,
                'status'       => 'pending',
                'is_approved'  => false,
            ]);

            Wallet::create([
                'owner_type' => User::class,
                'owner_id'   => $user->id,
                'balance'    => 0,
                'currency'   => 'USD',
            ]);

            return $user;
        });

        $token = $user->createToken('deliveryman-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration submitted. Awaiting admin approval.',
            'data'    => ['user' => $user, 'token' => $token],
        ], 201);
    }

    public function dashboard(Request $request)
    {
        $dm = $this->deliveryman($request);
        if (!$dm) return response()->json(['success' => false, 'message' => 'Deliveryman profile not found'], 404);

        $today = now()->startOfDay();

        $todayOrders   = Order::where('deliveryman_id', $dm->id)->whereDate('created_at', today())->count();
        $todayEarnings = DB::table('deliveryman_earnings')
            ->where('deliveryman_id', $dm->id)
            ->whereDate('created_at', today())
            ->sum('amount');

        $totalEarnings = DB::table('deliveryman_earnings')->where('deliveryman_id', $dm->id)->sum('amount');

        $activeOrder = Order::where('deliveryman_id', $dm->id)
            ->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery'])
            ->with(['vendor', 'user:id,name,phone'])
            ->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'status'         => $dm->status,
                'is_approved'    => $dm->is_approved,
                'today_orders'   => $todayOrders,
                'today_earnings' => $todayEarnings,
                'total_earnings' => $totalEarnings,
                'active_order'   => $activeOrder,
                'rating'         => $dm->rating,
            ],
        ]);
    }

    public function availableOrders(Request $request)
    {
        $dm = $this->deliveryman($request);
        if (!$dm || !$dm->is_approved) {
            return response()->json(['success' => false, 'message' => 'Not approved'], 403);
        }

        $orders = Order::whereNull('deliveryman_id')
            ->where('status', 'ready_for_pickup')
            ->with(['vendor', 'user:id,name'])
            ->latest()
            ->limit(20)
            ->get();

        return response()->json(['success' => true, 'data' => $orders]);
    }

    public function activeOrders(Request $request)
    {
        $dm = $this->deliveryman($request);

        $orders = Order::where('deliveryman_id', $dm->id)
            ->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery'])
            ->with(['vendor', 'user:id,name,phone'])
            ->get();

        return response()->json(['success' => true, 'data' => $orders]);
    }

    public function acceptOrder(Request $request, Order $order)
    {
        $dm = $this->deliveryman($request);

        if ($order->deliveryman_id) {
            return response()->json(['success' => false, 'message' => 'Order already assigned'], 422);
        }

        DB::transaction(function () use ($order, $dm, $request) {
            $order->update(['deliveryman_id' => $dm->id]);

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => $order->status,
                'note'       => 'Deliveryman accepted the order',
                'actor_id'   => $request->user()->id,
                'actor_type' => 'App\\Models\\User',
            ]);

            $dm->update(['status' => 'busy']);
        });

        return response()->json(['success' => true, 'message' => 'Order accepted']);
    }

    public function rejectOrder(Request $request, Order $order)
    {
        // Deliveryman simply doesn't take it — no action needed beyond logging
        return response()->json(['success' => true, 'message' => 'Order skipped']);
    }

    public function updateOrderStatus(Request $request, Order $order)
    {
        $dm = $this->deliveryman($request);

        if ($order->deliveryman_id !== $dm->id) {
            return response()->json(['success' => false, 'message' => 'Not your order'], 403);
        }

        $v = Validator::make($request->all(), [
            'status' => 'required|in:out_for_delivery,delivered',
            'note'   => 'nullable|string',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        DB::transaction(function () use ($order, $request, $dm) {
            $data = ['status' => $request->status];
            if ($request->status === 'delivered') {
                $data['delivered_at'] = now();
                // Credit delivery fee to deliveryman wallet
                $wallet = $request->user()->wallet;
                if ($wallet) {
                    $fee = $order->delivery_fee ?? 2;
                    $wallet->increment('balance', $fee);
                    DB::table('wallet_transactions')->insert([
                        'wallet_id'   => $wallet->id,
                        'type'        => 'credit',
                        'amount'      => $fee,
                        'description' => "Delivery fee for Order #{$order->order_number}",
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
                $dm->update(['status' => 'available']);
            }
            $order->update($data);

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => $request->status,
                'note'       => $request->note,
                'actor_id'   => $request->user()->id,
                'actor_type' => 'App\\Models\\User',
            ]);
        });

        return response()->json(['success' => true, 'message' => 'Status updated']);
    }

    public function updateLocation(Request $request)
    {
        $v = Validator::make($request->all(), [
            'lat'      => 'required|numeric',
            'lng'      => 'required|numeric',
            'order_id' => 'nullable|exists:orders,id',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $dm = $this->deliveryman($request);

        $dm->update(['current_lat' => $request->lat, 'current_lng' => $request->lng]);

        if ($request->order_id) {
            $order = Order::find($request->order_id);
            if ($order && $order->deliveryman_id === $dm->id) {
                OrderTracking::create([
                    'order_id' => $order->id,
                    'lat'      => $request->lat,
                    'lng'      => $request->lng,
                ]);

                event(new DeliveryLocationUpdated($order, $request->lat, $request->lng));
            }
        }

        return response()->json(['success' => true]);
    }

    public function toggleStatus(Request $request)
    {
        $dm = $this->deliveryman($request);

        $newStatus = $dm->status === 'available' ? 'offline' : 'available';
        $dm->update(['status' => $newStatus]);

        return response()->json(['success' => true, 'data' => ['status' => $newStatus]]);
    }

    public function earnings(Request $request)
    {
        $dm = $this->deliveryman($request);

        $todayEarnings = DB::table('wallet_transactions')
            ->where('wallet_id', $request->user()->wallet?->id)
            ->where('type', 'credit')
            ->whereDate('created_at', today())
            ->sum('amount');

        $monthlyEarnings = DB::table('wallet_transactions')
            ->where('wallet_id', $request->user()->wallet?->id)
            ->where('type', 'credit')
            ->whereMonth('created_at', now()->month)
            ->sum('amount');

        $totalOrders = Order::where('deliveryman_id', $dm->id)->where('status', 'delivered')->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'today_earnings'   => $todayEarnings,
                'monthly_earnings' => $monthlyEarnings,
                'total_orders'     => $totalOrders,
                'wallet_balance'   => $request->user()->wallet?->balance ?? 0,
                'rating'           => $dm->rating ?? 5.0,
            ],
        ]);
    }
}
