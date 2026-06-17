<?php

namespace App\Http\Controllers\Api\Modules;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EHealthController extends Controller
{
    // GET /ehealth/categories
    public function categories()
    {
        $categories = [
            ['id' => 'ambulance', 'name' => 'Ambulance',  'icon' => 'ambulance',     'description' => 'Emergency ambulance service 24/7'],
            ['id' => 'nurse',     'name' => 'Home Nurse', 'icon' => 'nurse',         'description' => 'Professional nurse at your home'],
            ['id' => 'doctor',    'name' => 'Doctor',     'icon' => 'doctor',        'description' => 'Book appointment with specialist'],
        ];

        return response()->json(['success' => true, 'data' => $categories]);
    }

    // GET /ehealth/doctors?specialization=doctor|nurse|ambulance&search=
    public function doctors(Request $request)
    {
        $query = DB::table('doctors')
            ->where('doctors.is_available', true)
            ->join('vendors', 'doctors.vendor_id', '=', 'vendors.id')
            ->select([
                'doctors.id', 'doctors.name', 'doctors.specialization',
                'doctors.qualification', 'doctors.experience_years',
                'doctors.consultation_fee', 'doctors.image', 'doctors.is_available',
                'vendors.name as clinic_name', 'vendors.address as clinic_address',
            ]);

        if ($request->filled('specialization')) {
            $query->where('doctors.specialization', 'like', '%' . $request->specialization . '%');
        }

        if ($request->filled('search')) {
            $q = '%' . $request->search . '%';
            $query->where(function ($sq) use ($q) {
                $sq->where('doctors.name', 'like', $q)
                   ->orWhere('doctors.specialization', 'like', $q);
            });
        }

        $doctors = $query->orderBy('doctors.experience_years', 'desc')->get();

        return response()->json(['success' => true, 'data' => $doctors]);
    }

    // GET /ehealth/doctors/{id}
    public function doctor($id)
    {
        $doctor = DB::table('doctors')
            ->where('doctors.id', $id)
            ->join('vendors', 'doctors.vendor_id', '=', 'vendors.id')
            ->select([
                'doctors.*',
                'vendors.name as clinic_name', 'vendors.address as clinic_address',
                'vendors.phone as clinic_phone', 'vendors.logo as clinic_logo',
            ])
            ->first();

        if (!$doctor) {
            return response()->json(['success' => false, 'message' => 'Doctor not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $doctor]);
    }

    // POST /ehealth/ambulance (auth) — emergency request
    public function requestAmbulance(Request $request)
    {
        $v = Validator::make($request->all(), [
            'pickup_address'  => 'required|array',
            'patient_name'    => 'required|string',
            'notes'           => 'nullable|string',
            'payment_method'  => 'required|in:wallet,cod',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user = $request->user();
        $fee  = DB::table('settings')->where('key', 'ambulance_base_fee')->value('value') ?? 25.00;

        $order = DB::transaction(function () use ($request, $user, $fee) {
            $order = Order::create([
                'order_number'    => 'AMB-' . strtoupper(Str::random(8)),
                'user_id'         => $user->id,
                'module_slug'     => 'ehealth',
                'status'          => 'pending',
                'payment_method'  => $request->payment_method,
                'payment_status'  => 'unpaid',
                'delivery_address'=> $request->pickup_address,
                'subtotal'        => $fee,
                'delivery_fee'    => 0,
                'total_amount'    => $fee,
                'note'            => json_encode([
                    'type'          => 'ambulance',
                    'patient_name'  => $request->patient_name,
                    'notes'         => $request->notes,
                ]),
                'placed_at' => now(),
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id, 'status' => 'pending',
                'note' => 'Ambulance requested', 'actor_id' => $user->id,
                'actor_type' => 'App\\Models\\User',
            ]);

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
                    'ehealth',
                );
            }
        } catch (\Throwable) {}
        return response()->json([
            'success' => true,
            'message' => 'Ambulance request sent! Help is on the way.',
            'data'    => ['order_number' => $order->order_number],
        ], 201);
    }

    // POST /ehealth/book (auth) — nurse or doctor appointment
    public function bookAppointment(Request $request)
    {
        $v = Validator::make($request->all(), [
            'type'            => 'required|in:nurse,doctor',
            'doctor_id'       => 'nullable|exists:doctors,id',
            'scheduled_at'    => 'required|date|after:now',
            'patient_name'    => 'required|string',
            'patient_phone'   => 'required|string',
            'address'         => 'required|array',
            'notes'           => 'nullable|string',
            'payment_method'  => 'required|in:wallet,cod',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user = $request->user();

        $fee = 10.00; // default nurse fee
        if ($request->type === 'doctor' && $request->doctor_id) {
            $doc = DB::table('doctors')->find($request->doctor_id);
            $fee = $doc?->consultation_fee ?? 15.00;
        }

        if ($request->payment_method === 'wallet') {
            if (!$user->wallet || $user->wallet->balance < $fee) {
                return response()->json(['success' => false, 'message' => 'Insufficient wallet balance'], 422);
            }
        }

        $order = DB::transaction(function () use ($request, $user, $fee) {
            $order = Order::create([
                'order_number'    => 'HLTH-' . strtoupper(Str::random(7)),
                'user_id'         => $user->id,
                'module_slug'     => 'ehealth',
                'status'          => 'pending',
                'payment_method'  => $request->payment_method,
                'payment_status'  => $request->payment_method === 'wallet' ? 'paid' : 'unpaid',
                'delivery_address'=> $request->address,
                'subtotal'        => $fee,
                'delivery_fee'    => 0,
                'total_amount'    => $fee,
                'note'            => json_encode([
                    'type'         => $request->type,
                    'doctor_id'    => $request->doctor_id,
                    'scheduled_at' => $request->scheduled_at,
                    'patient_name' => $request->patient_name,
                    'patient_phone'=> $request->patient_phone,
                    'notes'        => $request->notes,
                ]),
                'placed_at' => now(),
            ]);

            if ($request->doctor_id) {
                DB::table('appointments')->insert([
                    'order_id'     => $order->id,
                    'doctor_id'    => $request->doctor_id,
                    'user_id'      => $user->id,
                    'type'         => $request->type === 'nurse' ? 'home_nurse' : 'consultation',
                    'scheduled_at' => $request->scheduled_at,
                    'status'       => 'pending',
                    'notes'        => $request->notes,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id, 'status' => 'pending',
                'note' => ucfirst($request->type) . ' appointment booked',
                'actor_id' => $user->id, 'actor_type' => 'App\\Models\\User',
            ]);

            if ($request->payment_method === 'wallet') {
                $user->wallet->decrement('balance', $fee);
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
                    'ehealth',
                );
            }
        } catch (\Throwable) {}
        return response()->json([
            'success' => true,
            'message' => 'Appointment booked successfully!',
            'data'    => ['order_number' => $order->order_number, 'fee' => $fee],
        ], 201);
    }
}
