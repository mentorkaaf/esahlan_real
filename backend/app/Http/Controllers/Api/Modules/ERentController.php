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

class ERentController extends Controller
{
    // GET /erent/districts
    public function districts()
    {
        $districts = DB::table('districts')
            ->where('districts.status', 'active')
            ->leftJoin('properties', function ($join) {
                $join->on('properties.district_id', '=', 'districts.id')
                     ->where('properties.is_available', true);
            })
            ->select([
                'districts.id', 'districts.name', 'districts.name_so',
                'districts.slug', 'districts.image', 'districts.description',
                DB::raw('COUNT(properties.id) as property_count'),
            ])
            ->groupBy('districts.id', 'districts.name', 'districts.name_so', 'districts.slug', 'districts.image', 'districts.description')
            ->orderBy('districts.sort_order')
            ->get();

        return response()->json(['success' => true, 'data' => $districts]);
    }

    // GET /erent/properties?district_id=&type=&min_price=&max_price=&bedrooms=
    public function properties(Request $request)
    {
        $query = DB::table('properties')
            ->where('properties.is_available', true)
            ->join('districts', 'properties.district_id', '=', 'districts.id')
            ->select([
                'properties.id', 'properties.title', 'properties.type',
                'properties.bedrooms', 'properties.bathrooms', 'properties.kitchens', 'properties.living_rooms',
                'properties.floor', 'properties.year_built', 'properties.furnishing',
                'properties.monthly_rent', 'properties.deposit', 'properties.brokerage_fee',
                'properties.images', 'properties.reels', 'properties.amenities', 'properties.description',
                'properties.address', 'properties.is_available', 'properties.is_booked',
                'districts.name as district_name',
            ]);

        if ($request->filled('district_id')) $query->where('properties.district_id', $request->district_id);
        if ($request->filled('type')) $query->where('properties.type', $request->type);
        if ($request->filled('min_price')) $query->where('properties.monthly_rent', '>=', $request->min_price);
        if ($request->filled('max_price')) $query->where('properties.monthly_rent', '<=', $request->max_price);
        if ($request->filled('bedrooms')) $query->where('properties.bedrooms', $request->bedrooms);

        $properties = $query->orderBy('properties.monthly_rent')->paginate(20);

        $items = collect($properties->items())->map(fn($p) => $this->formatProperty($p));

        return response()->json([
            'success' => true,
            'data'    => $items,
            'meta'    => ['total' => $properties->total(), 'last_page' => $properties->lastPage()],
        ]);
    }

    // GET /erent/properties/{id}
    public function property($id)
    {
        $property = DB::table('properties')
            ->where('properties.id', $id)
            ->join('districts', 'properties.district_id', '=', 'districts.id')
            ->select(['properties.*', 'districts.name as district_name'])
            ->first();

        if (!$property) {
            return response()->json(['success' => false, 'message' => 'Property not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->formatProperty($property)]);
    }

    private function formatProperty($p): array
    {
        $images   = is_string($p->images)   ? (json_decode($p->images,   true) ?? []) : ($p->images   ?? []);
        $amenities= is_string($p->amenities) ? (json_decode($p->amenities,true) ?? []) : ($p->amenities ?? []);
        $reels    = is_string($p->reels ?? null) ? (json_decode($p->reels, true) ?? []) : ($p->reels ?? []);
        return [
            'id'           => $p->id,
            'title'        => $p->title,
            'type'         => $p->type,
            'description'  => $p->description,
            'address'      => $p->address ?? null,
            'district_name'=> $p->district_name,
            'bedrooms'     => (int)$p->bedrooms,
            'bathrooms'    => (int)$p->bathrooms,
            'kitchens'     => (int)($p->kitchens ?? 0),
            'living_rooms' => (int)($p->living_rooms ?? 0),
            'year_built'   => $p->year_built ? (int)$p->year_built : null,
            'floor'        => $p->floor ?? null,
            'furnishing'   => $p->furnishing ?? 'unfurnished',
            'monthly_rent' => (float)$p->monthly_rent,
            'deposit'      => (float)($p->deposit ?? 0),
            'brokerage_fee'=> (float)($p->brokerage_fee ?? 0),
            'images'       => $images,
            'thumbnail'    => $images[0] ?? null,
            'reels'        => is_array($reels) ? $reels : [],
            'amenities'    => is_array($amenities) ? $amenities : [],
            'is_available' => (bool)($p->is_available ?? true),
            'is_booked'    => (bool)($p->is_booked ?? false),
            // Booking type totals
            'full_rent_total'  => (float)$p->monthly_rent + (float)($p->deposit ?? 0) + (float)($p->brokerage_fee ?? 0),
            'carbuun_total'    => round(((float)$p->monthly_rent + (float)($p->deposit ?? 0) + (float)($p->brokerage_fee ?? 0)) * 0.30, 2),
        ];
    }

    // POST /erent/book (auth) — Full rent or carbuun

    public function reels()
    {
        $properties = \DB::table('properties')
            ->select('properties.id', 'properties.title', 'properties.images', 'properties.reels', 'properties.monthly_rent')
            ->leftJoin('districts', 'properties.district_id', '=', 'districts.id')
            ->addSelect('districts.name as district_name')
            ->whereNotNull('properties.reels')
            ->where('properties.reels', '!=', '[]')
            ->where('properties.reels', '!=', '')
            ->get();

        $result = [];
        foreach ($properties as $p) {
            $reels = is_string($p->reels) ? (json_decode($p->reels, true) ?? []) : ($p->reels ?? []);
            $images = is_string($p->images) ? (json_decode($p->images, true) ?? []) : ($p->images ?? []);
            if (!is_array($reels) || empty($reels)) continue;
            foreach ($reels as $rawUrl) {
                if (empty($rawUrl)) continue;
                $result[] = [
                    'property_id'    => $p->id,
                    'property_title' => $p->title,
                    'monthly_rent'   => $p->monthly_rent,
                    'district_name'  => $p->district_name ?? '',
                    'thumbnail'      => !empty($images) ? $this->resolveMediaUrl($images[0], 'properties') : null,
                    'video_url'      => $this->resolveStorageUrl($rawUrl, 'property-reels'),
                ];
            }
        }

        return response()->json(['status' => 'success', 'data' => $result]);
    }

    /**
     * Convert any stored URL/path to the canonical /storage/ URL.
     * Stored values may be full old URLs (esahlan.com/api/v1/img/folder/file.ext)
     * or raw filenames. Extracts the basename and rebuilds with Storage::url().
     */
    private function resolveStorageUrl(string $stored, string $folder): string
    {
        $filename = basename(parse_url($stored, PHP_URL_PATH) ?: $stored);
        return url('/api/v1/media?f=' . $folder . '/' . $filename);
    }

    /**
     * Same as resolveStorageUrl but routes through the media proxy
     * so CORS headers are present for Flutter Web.
     */
    private function resolveMediaUrl(string $stored, string $folder): string
    {
        $filename = basename(parse_url($stored, PHP_URL_PATH) ?: $stored);
        return url('/api/v1/media?f=' . $folder . '/' . $filename);
    }
    public function book(Request $request)
    {
        $v = Validator::make($request->all(), [
            'property_id'    => 'required|exists:properties,id',
            'booking_type'   => 'required|in:full_rent,carbuun,mobile_pay',
            'move_in_date'   => 'required|date|after:today',
            'duration_months'=> 'required|integer|min:1',
            'payment_method' => 'required|in:wallet,waafi_pay,mobile_pay',
            'note'           => 'nullable|string',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user     = $request->user();
        $property = DB::table('properties')->find($request->property_id);

        if (!$property || !$property->is_available) {
            return response()->json(['success' => false, 'message' => 'Property is not available'], 422);
        }
        if ($property->is_booked) {
            return response()->json(['success' => false, 'message' => 'Property is already booked'], 422);
        }

        $total = (float)$property->monthly_rent + (float)($property->deposit ?? 0) + (float)($property->brokerage_fee ?? 0);
        $amountDue = $request->booking_type === 'carbuun' ? round($total * 0.30, 2) : $total;
        $remaining  = $request->booking_type === 'carbuun' ? round($total * 0.70, 2) : 0;

        if ($request->payment_method === 'wallet') {
            $wCheck = Wallet::getOrCreateFor('App\\Models\\User', $user->id);
            if ($wCheck->balance < $amountDue) {
                return response()->json(['success' => false, 'message' => 'Insufficient wallet balance'], 422);
            }
        }

        $order = DB::transaction(function () use ($request, $user, $property, $amountDue, $remaining, $total) {
            $ref = 'RENT-' . strtoupper(Str::random(8));
            $order = Order::create([
                'order_number'    => $ref,
                'user_id'         => $user->id,
                'module_slug'     => 'erent',
                'status'          => 'pending',
                'payment_method'  => $request->payment_method,
                'payment_status'  => $request->payment_method === 'wallet' ? 'paid' : 'unpaid',
                'delivery_address'=> ['property_id' => $property->id],
                'subtotal'        => $property->monthly_rent,
                'delivery_fee'    => $property->brokerage_fee,
                'total_amount'    => $amountDue,
                'note'            => json_encode([
                    'property_title'  => $property->title,
                    'property_type'   => $property->type,
                    'booking_type'    => $request->booking_type,
                    'move_in_date'    => $request->move_in_date,
                    'duration_months' => $request->duration_months,
                    'monthly_rent'    => $property->monthly_rent,
                    'deposit'         => $property->deposit,
                    'brokerage_fee'   => $property->brokerage_fee,
                    'total_value'     => $total,
                    'amount_paid'     => $amountDue,
                    'amount_remaining'=> $remaining,
                    'note'            => $request->note,
                ]),
                'placed_at' => now(),
            ]);

            // Create property booking record
            DB::table('property_bookings')->insert([
                'property_id'     => $property->id,
                'user_id'         => $user->id,
                'order_id'        => $order->id,
                'booking_type'    => $request->booking_type,
                'status'          => 'pending',
                'move_in_date'    => $request->move_in_date,
                'duration_months' => $request->duration_months,
                'monthly_rent'    => $property->monthly_rent,
                'deposit'         => $property->deposit ?? 0,
                'brokerage_fee'   => $property->brokerage_fee ?? 0,
                'amount_paid'     => $amountDue,
                'amount_remaining'=> $remaining,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            // If carbuun, mark property as tentatively held
            if ($request->booking_type === 'carbuun') {
                DB::table('properties')->where('id', $property->id)->update([
                    'is_booked' => true, 'updated_at' => now()
                ]);
            } else {
                DB::table('properties')->where('id', $property->id)->update([
                    'is_available' => false, 'is_booked' => true, 'updated_at' => now()
                ]);
            }

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => 'pending',
                'note'       => $request->booking_type === 'carbuun' ? 'Carbuun deposit paid' : 'Full rent booking submitted',
                'actor_id'   => $user->id,
                'actor_type' => 'App\\Models\\User',
            ]);

            if ($request->payment_method === 'wallet') {
                $w = Wallet::getOrCreateFor('App\\Models\\User', $user->id);
                $w->debit($amountDue, "eRent {$request->booking_type}: {$property->title}", 'App\\Models\\Order', $order->id);
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
                    'erent',
                );
            }
        } catch (\Throwable) {}

        $message = $request->booking_type === 'carbuun'
            ? 'Carbuun successful! Property reserved. Remaining: $' . number_format($remaining, 2)
            : 'Booking submitted! Agent will contact you shortly.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => [
                'order_number'    => $order->order_number,
                'booking_type'    => $request->booking_type,
                'amount_paid'     => $amountDue,
                'amount_remaining'=> $remaining,
                'property_title'  => $property->title,
            ],
        ], 201);
    }

    // GET /erent/my-bookings (auth)
    public function myBookings(Request $request)
    {
        $bookings = DB::table('property_bookings')
            ->join('properties', 'property_bookings.property_id', '=', 'properties.id')
            ->join('orders', 'property_bookings.order_id', '=', 'orders.id')
            ->join('districts', 'properties.district_id', '=', 'districts.id')
            ->where('property_bookings.user_id', $request->user()->id)
            ->select([
                'property_bookings.*',
                'properties.title as property_title', 'properties.type as property_type',
                'properties.images as property_images', 'properties.monthly_rent',
                'districts.name as district_name',
                'orders.order_number', 'orders.payment_status', 'orders.status as order_status',
            ])
            ->orderByDesc('property_bookings.id')
            ->get();

        $result = $bookings->map(function ($b) {
            $imgs = is_string($b->property_images) ? (json_decode($b->property_images, true) ?? []) : [];
            return [
                'id'              => $b->id,
                'order_number'    => $b->order_number,
                'booking_type'    => $b->booking_type,
                'status'          => $b->status,
                'property_title'  => $b->property_title,
                'property_type'   => $b->property_type,
                'property_image'  => $imgs[0] ?? null,
                'district_name'   => $b->district_name,
                'monthly_rent'    => (float)$b->monthly_rent,
                'amount_paid'     => (float)$b->amount_paid,
                'amount_remaining'=> (float)$b->amount_remaining,
                'move_in_date'    => $b->move_in_date,
                'duration_months' => $b->duration_months,
                'payment_status'  => $b->payment_status,
                'created_at'      => $b->created_at,
            ];
        });

        return response()->json(['success' => true, 'data' => $result]);
    }

    // POST /erent/bookings/{id}/cancel (auth)
    public function cancelBooking(Request $request, $id)
    {
        $booking = DB::table('property_bookings')
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found'], 404);
        }
        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return response()->json(['success' => false, 'message' => 'Cannot cancel this booking'], 422);
        }

        DB::transaction(function () use ($booking, $request) {
            DB::table('property_bookings')->where('id', $booking->id)->update([
                'status'        => 'cancelled',
                'cancel_reason' => $request->reason ?? null,
                'updated_at'    => now(),
            ]);
            // Free the property
            DB::table('properties')->where('id', $booking->property_id)->update([
                'is_available' => true, 'is_booked' => false, 'updated_at' => now()
            ]);
            // Update order status
            DB::table('orders')->where('id', $booking->order_id)->update(['status' => 'cancelled', 'updated_at' => now()]);
        });

        return response()->json(['success' => true, 'message' => 'Booking cancelled. Refund will be processed.']);
    }

    // POST /erent/bookings/{id}/pay-remaining (auth)
    public function payRemaining(Request $request, $id)
    {
        $v = Validator::make($request->all(), [
            'payment_method' => 'required|in:wallet,waafi_pay,mobile_pay',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user    = $request->user();
        $booking = DB::table('property_bookings')
            ->where('id', $id)->where('user_id', $user->id)->first();

        if (!$booking) return response()->json(['success' => false, 'message' => 'Booking not found'], 404);
        if ($booking->booking_type !== 'carbuun') return response()->json(['success' => false, 'message' => 'Only Carbuun bookings have a remaining amount'], 422);
        if (!in_array($booking->status, ['pending', 'confirmed'])) return response()->json(['success' => false, 'message' => 'Cannot pay remaining for this booking'], 422);

        $remaining = (float)$booking->amount_remaining;
        if ($remaining <= 0) return response()->json(['success' => false, 'message' => 'No remaining balance due'], 422);

        if ($request->payment_method === 'wallet') {
            $wCheck = Wallet::getOrCreateFor('App\\Models\\User', $user->id);
            if ($wCheck->balance < $remaining) {
                return response()->json(['success' => false, 'message' => 'Insufficient wallet balance'], 422);
            }
        }

        DB::transaction(function () use ($booking, $user, $remaining, $request) {
            DB::table('property_bookings')->where('id', $booking->id)->update([
                'status'          => 'confirmed',
                'amount_paid'     => (float)$booking->amount_paid + $remaining,
                'amount_remaining'=> 0,
                'updated_at'      => now(),
            ]);
            DB::table('properties')->where('id', $booking->property_id)->update([
                'is_available' => false, 'is_booked' => true, 'updated_at' => now(),
            ]);
            DB::table('orders')->where('id', $booking->order_id)->update([
                'status'         => 'confirmed',
                'payment_status' => $request->payment_method === 'wallet' ? 'paid' : 'unpaid',
                'updated_at'     => now(),
            ]);
            if ($request->payment_method === 'wallet') {
                $title = DB::table('properties')->where('id', $booking->property_id)->value('title');
                $w = Wallet::getOrCreateFor('App\\Models\\User', $user->id);
                $w->debit($remaining, "eRent remaining 70%: {$title}", 'App\\Models\\Order', $booking->order_id);
            }
        });

        return response()->json(['success' => true, 'message' => 'Full rental confirmed! 🏠 Welcome to your new home.']);
    }

    // POST /erent/bookings/{id}/request-refund (auth)
    public function requestRefund(Request $request, $id)
    {
        $v = Validator::make($request->all(), [
            'reason' => 'required|string|min:5|max:500',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user    = $request->user();
        $booking = DB::table('property_bookings')
            ->where('id', $id)->where('user_id', $user->id)->first();

        if (!$booking) return response()->json(['success' => false, 'message' => 'Booking not found'], 404);
        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return response()->json(['success' => false, 'message' => 'Cannot request refund for this booking'], 422);
        }

        DB::table('property_bookings')->where('id', $id)->update([
            'status'        => 'refund_requested',
            'refund_reason' => $request->reason,
            'updated_at'    => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Refund request submitted. Admin will review within 24 hours.']);
    }
}
