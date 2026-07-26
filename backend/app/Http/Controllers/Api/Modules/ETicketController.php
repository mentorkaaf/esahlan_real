<?php

namespace App\Http\Controllers\Api\Modules;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use AppServicesoyaltyservice;

class ETicketController extends Controller
{
    // GET /eticket/airlines
    public function airlines()
    {
        $airlines = DB::table('airlines')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'code', 'logo', 'color']);
        return response()->json(['success' => true, 'data' => $airlines]);
    }

    // GET /eticket/routes
    public function routes()
    {
        $routes = DB::table('flight_routes')
            ->where('is_active', true)
            ->orderBy('from_city')
            ->get();
        return response()->json(['success' => true, 'data' => $routes]);
    }

    // GET /eticket/cities
    public function cities()
    {
        $fromRoutes = DB::table('flight_routes')
            ->where('is_active', true)
            ->select('from_city as city', 'from_code as code')
            ->union(DB::table('flight_routes')->where('is_active', true)->select('to_city as city', 'to_code as code'))
            ->orderBy('city')
            ->distinct()
            ->get();

        return response()->json(['success' => true, 'data' => $fromRoutes]);
    }

    // GET /eticket/active-dates?from=&to=
    public function activeDates(Request $request)
    {
        $from = $request->get('from', '');
        $to   = $request->get('to', '');

        $dates = DB::table('flights')
            ->when($from, fn($q) => $q->where('from_city', 'like', "%{$from}%"))
            ->when($to,   fn($q) => $q->where('to_city',   'like', "%{$to}%"))
            ->where('status', 'scheduled')
            ->where('available_seats', '>', 0)
            ->whereDate('departure_at', '>=', now()->toDateString())
            ->selectRaw('DATE(departure_at) as dep_date')
            ->distinct()
            ->pluck('dep_date');

        return response()->json(['success' => true, 'data' => $dates]);
    }

    // GET /eticket/search?from=&to=&date=&passengers=&seat_class=economy
    public function search(Request $request)
    {
        $v = Validator::make($request->all(), [
            'from'       => 'required|string',
            'to'         => 'required|string',
            'date'       => 'nullable|date',
            'passengers' => 'nullable|integer|min:1|max:9',
            'seat_class' => 'nullable|in:economy,business,first,mobile_pay',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $passengers = $request->get('passengers', 1);
        $seatClass  = $request->get('seat_class', 'economy');

        $query = DB::table('flights')
            ->where('flights.from_city', 'like', '%' . $request->from . '%')
            ->where('flights.to_city', 'like', '%' . $request->to . '%')
            ->where('flights.available_seats', '>=', $passengers)
            ->where('flights.status', 'scheduled')
            ->leftJoin('airlines', 'flights.airline_id', '=', 'airlines.id')
            ->select([
                'flights.id', 'flights.flight_number', 'flights.airline',
                'flights.from_city', 'flights.to_city', 'flights.from_code', 'flights.to_code',
                'flights.departure_at', 'flights.arrival_at', 'flights.available_seats',
                'flights.total_seats', 'flights.duration_minutes', 'flights.aircraft_type',
                'flights.seat_classes',
                'airlines.name as airline_name', 'airlines.logo as airline_logo', 'airlines.color as airline_color',
            ])
            ->orderBy('flights.departure_at');

        if ($request->filled('date')) {
            $query->whereDate('flights.departure_at', $request->date);
        }

        $flights = $query->get();

        $result = $flights->map(function ($flight) use ($seatClass, $passengers) {
            $classes = json_decode($flight->seat_classes, true) ?? [];
            $price   = $classes[$seatClass] ?? ($classes['economy'] ?? 0);
            $dur = null;
            if ($flight->duration_minutes) {
                $h = floor($flight->duration_minutes / 60);
                $m = $flight->duration_minutes % 60;
                $dur = $h . 'h ' . $m . 'm';
            }
            return [
                'id'             => $flight->id,
                'flight_number'  => $flight->flight_number,
                'airline'        => $flight->airline_name ?? $flight->airline,
                'airline_logo'   => $flight->airline_logo,
                'airline_color'  => $flight->airline_color ?? '#1a73e8',
                'from_city'      => $flight->from_city,
                'to_city'        => $flight->to_city,
                'from_code'      => $flight->from_code,
                'to_code'        => $flight->to_code,
                'departure_at'   => $flight->departure_at,
                'arrival_at'     => $flight->arrival_at,
                'duration'       => $dur,
                'available_seats'=> $flight->available_seats,
                'aircraft_type'  => $flight->aircraft_type,
                'classes'        => $classes,
                'price_per_person'=> (float)$price,
                'total_price'    => (float)($price * $passengers),
                'seat_class'     => $seatClass,
            ];
        });

        return response()->json(['success' => true, 'data' => $result]);
    }

    // POST /eticket/book (auth)
    public function book(Request $request)
    {
        $v = Validator::make($request->all(), [
            'flight_id'          => 'required|exists:flights,id',
            'seat_class'         => 'required|in:economy,business,first,mobile_pay',
            'payment_method'     => 'required|in:wallet,waafi_pay,cod,mobile_pay',
            'passengers'         => 'required|array|min:1',
            'passengers.*.name'  => 'required|string',
            'passengers.*.passport_number' => 'required|string',
            'passengers.*.nationality'     => 'required|string',
            'passengers.*.dob'             => 'required|date',
            'passengers.*.type'            => 'required|in:adult,child,infant,mobile_pay',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user   = $request->user();
        $flight = DB::table('flights')->find($request->flight_id);

        if (!$flight || $flight->status === 'cancelled') {
            return response()->json(['success' => false, 'message' => 'Flight not available'], 422);
        }
        if ($flight->available_seats < count($request->passengers)) {
            return response()->json(['success' => false, 'message' => 'Not enough seats available'], 422);
        }

        $classes   = json_decode($flight->seat_classes, true) ?? [];
        $price     = $classes[$request->seat_class] ?? ($classes['economy'] ?? 0);
        $total     = $price * count($request->passengers);

        // Points redeem (2x multiplier for eTicket)
        $loyalty = LoyaltyService::processOrderRequest($request, $user->id, $total, 'eticket');
        $total   = round(max(0, $total - $loyalty['points_discount']), 2);

        if ($request->payment_method === 'wallet') {
            if (!$user->wallet || $user->wallet->balance < $total) {
                return response()->json(['success' => false, 'message' => 'Insufficient wallet balance'], 422);
            }
        }

        $order = DB::transaction(function () use ($request, $user, $flight, $total, $price, $loyalty) {
            $ref = 'TKT-' . strtoupper(Str::random(6));

            $order = Order::create([
                'order_number'    => $ref,
                'user_id'         => $user->id,
                'module_slug'     => 'eticket',
                'status'          => 'confirmed',
                'payment_method'  => $request->payment_method,
                'payment_status'  => in_array($request->payment_method, ['wallet','waafi_pay']) ? 'paid' : 'unpaid',
                'delivery_address'=> ['flight_id' => $flight->id],
                'subtotal'        => $total,
                'delivery_fee'    => 0,
                'total_amount'    => $total,
                'points_used'     => $loyalty['points_used'],
                'points_discount' => $loyalty['points_discount'],
                'note'            => json_encode([
                    'flight_number' => $flight->flight_number,
                    'from'          => $flight->from_city,
                    'from_code'     => $flight->from_code,
                    'to'            => $flight->to_city,
                    'to_code'       => $flight->to_code,
                    'departure'     => $flight->departure_at,
                    'arrival'       => $flight->arrival_at,
                    'seat_class'    => $request->seat_class,
                    'price_per_pax' => $price,
                    'passengers'    => $request->passengers,
                    'airline'       => $flight->airline,
                ]),
                'placed_at' => now(),
            ]);

            DB::table('flight_bookings')->insert([
                'order_id'          => $order->id,
                'flight_id'         => $flight->id,
                'passengers'        => json_encode($request->passengers),
                'seat_class'        => $request->seat_class,
                'total_passengers'  => count($request->passengers),
                'booking_reference' => $ref,
                'created_at'        => now(),
            ]);

            DB::table('flights')->where('id', $flight->id)
                ->decrement('available_seats', count($request->passengers));

            OrderStatusHistory::create([
                'order_id' => $order->id, 'status' => 'confirmed',
                'note' => 'Flight ticket booked', 'actor_id' => $user->id,
                'actor_type' => 'App\\Models\\User',
            ]);

            if ($request->payment_method === 'wallet') {
                $user->wallet->decrement('balance', $total);
                DB::table('wallet_transactions')->insert([
                    'wallet_id'   => $user->wallet->id,
                    'type'        => 'debit',
                    'amount'      => $total,
                    'description' => "eTicket: {$ref}",
                    'reference_id'=> $order->id,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
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
                    'eticket',
                );
            }
        } catch (\Throwable) {}
        return response()->json([
            'success' => true,
            'message' => 'Ticket booked successfully! ✈️',
            'data'    => [
                'order_number'     => $order->order_number,
                'total'            => $total,
                'passengers'       => count($request->passengers),
                'seat_class'       => $request->seat_class,
                'flight_number'    => $flight->flight_number,
                'from'             => $flight->from_city,
                'to'               => $flight->to_city,
                'departure'        => $flight->departure_at,
            ],
        ], 201);
    }

    // GET /eticket/my-bookings (auth)
    public function myBookings(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->where('module_slug', 'eticket')
            ->orderByDesc('placed_at')
            ->get();

        $result = $orders->map(function ($order) {
            $note = is_string($order->note) ? json_decode($order->note, true) : (array)$order->note;
            return [
                'id'           => $order->id,
                'order_number' => $order->order_number,
                'status'       => $order->status,
                'total_amount' => (float)$order->total_amount,
                'payment_status'=> $order->payment_status,
                'placed_at'    => $order->placed_at,
                'flight_number'=> $note['flight_number'] ?? null,
                'from'         => $note['from'] ?? null,
                'from_code'    => $note['from_code'] ?? null,
                'to'           => $note['to'] ?? null,
                'to_code'      => $note['to_code'] ?? null,
                'departure'    => $note['departure'] ?? null,
                'arrival'      => $note['arrival'] ?? null,
                'seat_class'   => $note['seat_class'] ?? null,
                'passengers'   => $note['passengers'] ?? [],
                'airline'      => $note['airline'] ?? null,
            ];
        });

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function home()
    {
        $airlines = \DB::table('airlines')->where('is_active', true)->limit(10)->get();
        $routes   = \DB::table('flight_routes')->select('from_city','to_city','from_code','to_code')->distinct()->limit(10)->get();
        return response()->json(['success' => true, 'data' => ['airlines' => $airlines, 'popular_routes' => $routes]]);
    }

    public function flightDetail($id)
    {
        $flight = \DB::table('flights as f')
            ->leftJoin('flight_routes as r', 'r.id', '=', 'f.route_id')
            ->leftJoin('airlines as a', 'a.id', '=', 'f.airline_id')
            ->select('f.*', 'r.origin_city', 'r.destination_city', 'a.name as airline_name', 'a.logo as airline_logo')
            ->where('f.id', $id)->first();
        if (!$flight) return response()->json(['success' => false, 'message' => 'Not found'], 404);
        return response()->json(['success' => true, 'data' => $flight]);
    }

    public function myBookingDetail($id)
    {
        $user = request()->user();
        if (!$user) return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        $b = \DB::table('flight_bookings')->where('id', $id)->where('user_id', $user->id)->first();
        if (!$b) return response()->json(['success' => false, 'message' => 'Not found'], 404);
        return response()->json(['success' => true, 'data' => $b]);
    }

}
