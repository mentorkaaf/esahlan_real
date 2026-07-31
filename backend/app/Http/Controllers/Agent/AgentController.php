<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AgentController extends Controller
{
    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function dashboard(Request $request)
    {
        $agentId = $request->user()->id;

        $total    = DB::table('properties')->where('agent_user_id', $agentId)->count();
        $active   = DB::table('properties')->where('agent_user_id', $agentId)->where('is_available', true)->where('is_booked', false)->count();
        $rented   = DB::table('properties')->where('agent_user_id', $agentId)->where('is_available', false)->count();
        $booked   = DB::table('properties')->where('agent_user_id', $agentId)->where('is_booked', true)->count();

        // Commission earned (wallet transactions of type credit related to agent)
        $wallet = Wallet::getOrCreateFor('App\\Models\\User', $agentId);
        $totalEarned = DB::table('wallet_transactions')
            ->where('wallet_id', $wallet->id)
            ->where('type', 'credit')
            ->where('description', 'like', '%commission%')
            ->sum('amount');

        $monthEarned = DB::table('wallet_transactions')
            ->where('wallet_id', $wallet->id)
            ->where('type', 'credit')
            ->where('description', 'like', '%commission%')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount');

        // Recent properties
        $recent = DB::table('properties')
            ->where('agent_user_id', $agentId)
            ->join('districts', 'properties.district_id', '=', 'districts.id')
            ->select('properties.id', 'properties.title', 'properties.type', 'properties.monthly_rent',
                     'properties.is_available', 'properties.is_booked', 'properties.images',
                     'properties.created_at', 'districts.name as district_name')
            ->orderByDesc('properties.created_at')
            ->limit(5)
            ->get()
            ->map(fn($p) => $this->formatMini($p));

        // Recent commissions
        $recentCommissions = DB::table('wallet_transactions')
            ->where('wallet_id', $wallet->id)
            ->where('type', 'credit')
            ->where('description', 'like', '%commission%')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Phase 4: Request analytics
        $requestsTotal      = DB::table('house_requests')->where('agent_user_id', $agentId)->count();
        $requestsActive     = DB::table('house_requests')->where('agent_user_id', $agentId)
            ->whereNotIn('status', ['completed', 'cancelled'])->count();
        $requestsCompleted  = DB::table('house_requests')->where('agent_user_id', $agentId)
            ->where('status', 'completed')->count();
        $recsTotal          = DB::table('request_recommendations')->where('agent_user_id', $agentId)->count();
        $recsAccepted       = DB::table('request_recommendations')->where('agent_user_id', $agentId)
            ->where('status', 'accepted')->count();
        $conversionRate     = $recsTotal > 0 ? round(($recsAccepted / $recsTotal) * 100, 1) : 0;
        $viewingsTotal      = DB::table('request_viewings')->where('agent_user_id', $agentId)->count();
        $viewingsConfirmed  = DB::table('request_viewings')->where('agent_user_id', $agentId)
            ->where('status', 'confirmed')->count();

        // Recent requests assigned to this agent
        $recentRequests = DB::table('house_requests')
            ->where('house_requests.agent_user_id', $agentId)
            ->leftJoin('users as cust', 'house_requests.customer_user_id', '=', 'cust.id')
            ->leftJoin('districts', 'house_requests.district_id', '=', 'districts.id')
            ->select([
                'house_requests.id', 'house_requests.request_ref', 'house_requests.purpose',
                'house_requests.type', 'house_requests.status', 'house_requests.created_at',
                'cust.name as customer_name', 'districts.name as district_name',
            ])
            ->orderByDesc('house_requests.id')->limit(5)->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'stats' => [
                    'total_listings'      => $total,
                    'active_listings'     => $active,
                    'rented'              => $rented,
                    'booked'              => $booked,
                    'total_earned'        => (float)$totalEarned,
                    'month_earned'        => (float)$monthEarned,
                    'wallet_balance'      => (float)$wallet->balance,
                    // Phase 4 request stats
                    'requests_total'      => $requestsTotal,
                    'requests_active'     => $requestsActive,
                    'requests_completed'  => $requestsCompleted,
                    'recs_total'          => $recsTotal,
                    'recs_accepted'       => $recsAccepted,
                    'conversion_rate'     => $conversionRate,
                    'viewings_total'      => $viewingsTotal,
                    'viewings_confirmed'  => $viewingsConfirmed,
                ],
                'recent_properties'   => $recent,
                'recent_commissions'  => $recentCommissions,
                'recent_requests'     => $recentRequests,
            ],
        ]);
    }

    // ── Properties ────────────────────────────────────────────────────────────

    public function properties(Request $request)
    {
        $agentId = $request->user()->id;
        $status  = $request->query('status'); // active | rented | all

        $query = DB::table('properties')
            ->where('properties.agent_user_id', $agentId)
            ->join('districts', 'properties.district_id', '=', 'districts.id')
            ->select('properties.*', 'districts.name as district_name');

        if ($status === 'active') {
            $query->where('properties.is_available', true)->where('properties.is_booked', false);
        } elseif ($status === 'rented') {
            $query->where('properties.is_available', false);
        }

        $props = $query->orderByDesc('properties.created_at')->paginate(20);
        $items = collect($props->items())->map(fn($p) => $this->formatFull($p));

        return response()->json([
            'success' => true,
            'data'    => $items,
            'meta'    => ['total' => $props->total(), 'last_page' => $props->lastPage()],
        ]);
    }

    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'title'        => 'required|string|max:200',
            'description'  => 'nullable|string|max:2000',
            'type'         => 'required|in:apartment,house,villa,room,office,shop',
            'district_id'  => 'required|exists:districts,id',
            'address'      => 'nullable|string|max:255',
            'bedrooms'     => 'required|integer|min:0|max:20',
            'bathrooms'    => 'required|integer|min:0|max:20',
            'kitchens'     => 'nullable|integer|min:0|max:10',
            'living_rooms' => 'nullable|integer|min:0|max:10',
            'floor'        => 'nullable|integer|min:0|max:100',
            'area_sqm'     => 'nullable|numeric|min:0',
            'furnishing'   => 'nullable|in:unfurnished,semi_furnished,furnished',
            'monthly_rent' => 'required|numeric|min:0',
            'deposit'      => 'nullable|numeric|min:0',
            'brokerage_fee'=> 'nullable|numeric|min:0',
            'amenities'      => 'nullable|array',
            'amenities_json' => 'nullable|string',
            'images.*'       => 'nullable|image|max:5120',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('properties', 'public');
                $images[] = $path;
            }
        }

        $id = DB::table('properties')->insertGetId([
            'agent_user_id' => $request->user()->id,
            'district_id'   => $request->district_id,
            'title'         => $request->title,
            'description'   => $request->description,
            'type'          => $request->type,
            'address'       => $request->address,
            'bedrooms'      => $request->bedrooms,
            'bathrooms'     => $request->bathrooms,
            'kitchens'      => $request->kitchens ?? 0,
            'living_rooms'  => $request->living_rooms ?? 0,
            'floor'         => $request->floor,
            'area_sqm'      => $request->area_sqm,
            'furnishing'    => $request->furnishing ?? 'unfurnished',
            'monthly_rent'  => $request->monthly_rent,
            'deposit'       => $request->deposit ?? 0,
            'brokerage_fee' => $request->brokerage_fee ?? 0,
            'amenities'     => json_encode(
                $request->amenities
                    ?? ($request->amenities_json
                        ? array_filter(explode(',', $request->amenities_json))
                        : [])
            ),
            'images'        => json_encode($images),
            'is_available'  => true,
            'is_booked'     => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $property = DB::table('properties')
            ->join('districts', 'properties.district_id', '=', 'districts.id')
            ->where('properties.id', $id)
            ->select('properties.*', 'districts.name as district_name')
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Property listed successfully',
            'data'    => $this->formatFull($property),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $property = DB::table('properties')
            ->where('id', $id)
            ->where('agent_user_id', $request->user()->id)
            ->first();

        if (!$property) return response()->json(['success' => false, 'message' => 'Property not found'], 404);

        $v = Validator::make($request->all(), [
            'title'        => 'sometimes|string|max:200',
            'description'  => 'nullable|string|max:2000',
            'type'         => 'sometimes|in:apartment,house,villa,room,office,shop',
            'district_id'  => 'sometimes|exists:districts,id',
            'address'      => 'nullable|string|max:255',
            'bedrooms'     => 'sometimes|integer|min:0|max:20',
            'bathrooms'    => 'sometimes|integer|min:0|max:20',
            'monthly_rent' => 'sometimes|numeric|min:0',
            'deposit'      => 'nullable|numeric|min:0',
            'brokerage_fee'=> 'nullable|numeric|min:0',
            'furnishing'   => 'nullable|in:unfurnished,semi_furnished,furnished',
            'amenities'    => 'nullable|array',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $data = array_filter($request->only([
            'title', 'description', 'type', 'district_id', 'address',
            'bedrooms', 'bathrooms', 'kitchens', 'living_rooms',
            'floor', 'area_sqm', 'furnishing', 'monthly_rent', 'deposit', 'brokerage_fee',
        ]), fn($v) => $v !== null);

        if ($request->has('amenities')) {
            $data['amenities'] = json_encode($request->amenities);
        }

        // Handle new image uploads
        if ($request->hasFile('images')) {
            $images = [];
            foreach ($request->file('images') as $file) {
                $images[] = $file->store('properties', 'public');
            }
            $data['images'] = json_encode($images);
        }

        $data['updated_at'] = now();
        DB::table('properties')->where('id', $id)->update($data);

        return response()->json(['success' => true, 'message' => 'Property updated']);
    }

    public function markRented(Request $request, $id)
    {
        $updated = DB::table('properties')
            ->where('id', $id)
            ->where('agent_user_id', $request->user()->id)
            ->update(['is_available' => false, 'is_booked' => true, 'updated_at' => now()]);

        if (!$updated) return response()->json(['success' => false, 'message' => 'Property not found'], 404);

        return response()->json(['success' => true, 'message' => 'Property marked as rented']);
    }

    public function markAvailable(Request $request, $id)
    {
        $updated = DB::table('properties')
            ->where('id', $id)
            ->where('agent_user_id', $request->user()->id)
            ->update(['is_available' => true, 'is_booked' => false, 'updated_at' => now()]);

        if (!$updated) return response()->json(['success' => false, 'message' => 'Property not found'], 404);

        return response()->json(['success' => true, 'message' => 'Property marked as available']);
    }

    public function destroy(Request $request, $id)
    {
        $property = DB::table('properties')
            ->where('id', $id)
            ->where('agent_user_id', $request->user()->id)
            ->first();

        if (!$property) return response()->json(['success' => false, 'message' => 'Property not found'], 404);

        DB::table('properties')->where('id', $id)->delete();

        return response()->json(['success' => true, 'message' => 'Property deleted']);
    }

    // ── Districts ─────────────────────────────────────────────────────────────

    public function districts()
    {
        $districts = DB::table('districts')
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->select('id', 'name', 'name_so', 'slug')
            ->get();

        return response()->json(['success' => true, 'data' => $districts]);
    }

    // ── Wallet ────────────────────────────────────────────────────────────────

    public function wallet(Request $request)
    {
        $wallet = Wallet::getOrCreateFor('App\\Models\\User', $request->user()->id);

        $transactions = DB::table('wallet_transactions')
            ->where('wallet_id', $wallet->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'balance'      => (float)$wallet->balance,
                'currency'     => $wallet->currency ?? 'USD',
                'transactions' => $transactions,
            ],
        ]);
    }

    // ── House Requests ────────────────────────────────────────────────────────

    public function houseRequests(Request $request)
    {
        $agentId         = $request->user()->id;
        $agentDistrictId = $request->user()->district_id;

        $query = DB::table('house_requests')
            ->join('users as customers', 'house_requests.customer_user_id', '=', 'customers.id')
            ->leftJoin('districts', 'house_requests.district_id', '=', 'districts.id')
            // Show open requests + requests assigned to this agent
            ->where(function ($q) use ($agentId) {
                $q->where('house_requests.status', 'open')
                  ->orWhere('house_requests.agent_user_id', $agentId);
            })
            ->whereNotIn('house_requests.status', ['completed', 'cancelled'])
            ->select([
                'house_requests.id', 'house_requests.request_ref',
                'house_requests.purpose', 'house_requests.type', 'house_requests.bedrooms',
                'house_requests.budget_min', 'house_requests.budget_max',
                'house_requests.move_in_date', 'house_requests.description',
                'house_requests.status', 'house_requests.agent_user_id',
                'house_requests.assigned_at', 'house_requests.created_at',
                'customers.name as customer_name', 'customers.phone as customer_phone',
                'districts.name as district_name',
            ]);

        // My assigned requests first, then district requests, then others
        $query->orderByRaw('CASE WHEN house_requests.agent_user_id = ? THEN 0 ELSE 1 END', [$agentId]);
        if ($agentDistrictId) {
            $query->orderByRaw('CASE WHEN house_requests.district_id = ? THEN 0 ELSE 1 END', [$agentDistrictId]);
        }

        $results = $query->orderByDesc('house_requests.id')->paginate(30);

        return response()->json([
            'success' => true,
            'data'    => $results->items(),
            'meta'    => ['total' => $results->total(), 'last_page' => $results->lastPage()],
        ]);
    }

    public function assignRequest(Request $request, $id)
    {
        $agentId = $request->user()->id;
        $updated = DB::table('house_requests')
            ->where('id', $id)
            ->where('status', 'open')
            ->whereNull('agent_user_id')
            ->update([
                'status'        => 'assigned',
                'agent_user_id' => $agentId,
                'assigned_at'   => now(),
                'updated_at'    => now(),
            ]);

        if (!$updated) {
            return response()->json(['success' => false, 'message' => 'Request already taken or not available'], 422);
        }

        try {
            $req      = DB::table('house_requests')->where('id', $id)->first();
            $customer = DB::table('users')->where('id', $req->customer_user_id)->first();
            $agent    = $request->user();
            if ($customer?->fcm_token) {
                FcmService::send($customer->fcm_token, 'Agent Found! 🎉',
                    "Agent {$agent->name} has accepted your request {$req->request_ref}",
                    ['type' => 'house_request', 'id' => (string) $id]);
            }
        } catch (\Throwable $e) {
            \Log::warning('[HouseRequest Assign FCM] ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Request assigned to you']);
    }

    public function updateRequestStatus(Request $request, $id)
    {
        $v = Validator::make($request->all(), [
            'status' => 'required|in:searching,matched,completed,cancelled',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $agentId = $request->user()->id;
        $req     = DB::table('house_requests')->where('id', $id)->first();

        if (!$req) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        if ($req->agent_user_id != $agentId) {
            return response()->json(['success' => false, 'message' => 'Not your request'], 403);
        }

        DB::table('house_requests')->where('id', $id)->update([
            'status'     => $request->status,
            'updated_at' => now(),
        ]);

        try {
            $customer = DB::table('users')->where('id', $req->customer_user_id)->first();
            $msgs     = [
                'searching' => ['Searching...', "Your agent is actively searching ({$req->request_ref})"],
                'matched'   => ['Match Found! 🏠', "Great news! Your agent found a match for {$req->request_ref}"],
                'completed' => ['Request Completed ✓', "Your request {$req->request_ref} has been completed!"],
                'cancelled' => ['Request Cancelled', "Your request {$req->request_ref} was cancelled by the agent."],
            ];
            if ($customer?->fcm_token && isset($msgs[$request->status])) {
                [$title, $body] = $msgs[$request->status];
                FcmService::send($customer->fcm_token, $title, $body, ['type' => 'house_request', 'id' => (string) $id]);
            }
        } catch (\Throwable $e) {
            \Log::warning('[HouseRequest Status FCM] ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Status updated']);
    }

    // Kept for backward compatibility
    public function contactRequest(Request $request, $id)
    {
        return $this->assignRequest($request, $id);
    }

    public function closeRequest(Request $request, $id)
    {
        $agentId = $request->user()->id;
        DB::table('house_requests')
            ->where('id', $id)
            ->where(function ($q) use ($agentId) {
                $q->where('agent_user_id', $agentId)->orWhere('status', 'open');
            })
            ->update(['status' => 'cancelled', 'updated_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Request cancelled']);
    }

    // ── Phase 2: Recommendations ─────────────────────────────────────────────

    public function recommendProperty(Request $request, $id)
    {
        $v = Validator::make($request->all(), [
            'property_id'   => 'required|exists:properties,id',
            'message'       => 'nullable|string|max:500',
            'offered_price' => 'nullable|numeric|min:0',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $agentId = $request->user()->id;
        $req     = DB::table('house_requests')->where('id', $id)->where('agent_user_id', $agentId)->first();
        if (!$req) return response()->json(['success' => false, 'message' => 'Not your request'], 403);

        // Verify property belongs to this agent
        $prop = DB::table('properties')->where('id', $request->property_id)->where('agent_user_id', $agentId)->first();
        if (!$prop) return response()->json(['success' => false, 'message' => 'Property not yours'], 403);

        $recId = DB::table('request_recommendations')->insertGetId([
            'request_id'    => $id,
            'agent_user_id' => $agentId,
            'property_id'   => $request->property_id,
            'message'       => $request->message,
            'offered_price' => $request->offered_price,
            'status'        => 'pending',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Update request status to 'matched'
        DB::table('house_requests')->where('id', $id)->update(['status' => 'matched', 'updated_at' => now()]);

        // Notify customer
        try {
            $customer = DB::table('users')->where('id', $req->customer_user_id)->first();
            if ($customer?->fcm_token) {
                FcmService::send($customer->fcm_token, 'Property Found! 🏠',
                    "Your agent found a great match for {$req->request_ref} — {$prop->title}",
                    ['type' => 'recommendation', 'request_id' => (string) $id]);
            }
        } catch (\Throwable $e) {}

        return response()->json(['success' => true, 'message' => 'Recommendation sent', 'data' => ['id' => $recId]]);
    }

    public function getRecommendations(Request $request, $id)
    {
        $agentId = $request->user()->id;
        $recs    = DB::table('request_recommendations')
            ->where('request_id', $id)->where('agent_user_id', $agentId)
            ->leftJoin('properties', 'request_recommendations.property_id', '=', 'properties.id')
            ->leftJoin('districts', 'properties.district_id', '=', 'districts.id')
            ->select([
                'request_recommendations.id', 'request_recommendations.message',
                'request_recommendations.status', 'request_recommendations.created_at',
                'properties.title', 'properties.monthly_rent', 'properties.bedrooms',
                'properties.type as property_type', 'properties.images',
                'districts.name as district_name',
            ])
            ->orderByDesc('request_recommendations.id')
            ->get()
            ->map(function ($r) {
                $imgs = is_string($r->images) ? (json_decode($r->images, true) ?? []) : [];
                return array_merge((array) $r, [
                    'thumbnail' => !empty($imgs) ? url('/api/v1/media?f=properties/' . basename($imgs[0])) : null,
                ]);
            });

        return response()->json(['success' => true, 'data' => $recs]);
    }

    // ── Phase 2: Viewings ────────────────────────────────────────────────────

    public function scheduleViewing(Request $request, $id)
    {
        $v = Validator::make($request->all(), [
            'property_id'       => 'required|exists:properties,id',
            'proposed_at'       => 'required|date|after:now',
            'recommendation_id' => 'nullable|exists:request_recommendations,id',
            'notes'             => 'nullable|string|max:300',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $agentId = $request->user()->id;
        $req     = DB::table('house_requests')->where('id', $id)->where('agent_user_id', $agentId)->first();
        if (!$req) return response()->json(['success' => false, 'message' => 'Not your request'], 403);

        $viewId = DB::table('request_viewings')->insertGetId([
            'request_id'        => $id,
            'recommendation_id' => $request->recommendation_id,
            'agent_user_id'     => $agentId,
            'property_id'       => $request->property_id,
            'proposed_at'       => $request->proposed_at,
            'status'            => 'proposed',
            'notes'             => $request->notes,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        DB::table('house_requests')->where('id', $id)->update(['status' => 'viewing_scheduled', 'updated_at' => now()]);

        try {
            $customer = DB::table('users')->where('id', $req->customer_user_id)->first();
            if ($customer?->fcm_token) {
                $dt = \Carbon\Carbon::parse($request->proposed_at)->format('D, d M Y H:i');
                FcmService::send($customer->fcm_token, 'Viewing Scheduled 📅',
                    "Your agent scheduled a property viewing on {$dt}. Please confirm!",
                    ['type' => 'viewing', 'request_id' => (string) $id]);
            }
        } catch (\Throwable $e) {}

        return response()->json(['success' => true, 'message' => 'Viewing scheduled', 'data' => ['id' => $viewId]]);
    }

    public function updateViewingStatus(Request $request, $viewId)
    {
        $v = Validator::make($request->all(), ['status' => 'required|in:cancelled,completed']);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $agentId = $request->user()->id;
        $viewing = DB::table('request_viewings')->where('id', $viewId)->where('agent_user_id', $agentId)->first();
        if (!$viewing) return response()->json(['success' => false, 'message' => 'Not found'], 404);

        DB::table('request_viewings')->where('id', $viewId)->update(['status' => $request->status, 'updated_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Viewing updated']);
    }

    // ── Phase 3: Chat ────────────────────────────────────────────────────────

    public function getMessagesAgent(Request $request, $id)
    {
        $agentId = $request->user()->id;
        $req     = DB::table('house_requests')->where('id', $id)->where('agent_user_id', $agentId)->first();
        if (!$req) return response()->json(['success' => false, 'message' => 'Not your request'], 403);

        // Mark customer messages as read
        DB::table('request_messages')
            ->where('request_id', $id)->where('sender_role', 'customer')->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = DB::table('request_messages')
            ->where('request_id', $id)
            ->join('users', 'request_messages.sender_id', '=', 'users.id')
            ->select('request_messages.*', 'users.name as sender_name')
            ->orderBy('request_messages.id')
            ->get();

        return response()->json(['success' => true, 'data' => $messages]);
    }

    public function sendMessageAgent(Request $request, $id)
    {
        $v = Validator::make($request->all(), ['message' => 'required|string|max:1000']);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $agentId = $request->user()->id;
        $req     = DB::table('house_requests')->where('id', $id)->where('agent_user_id', $agentId)->first();
        if (!$req) return response()->json(['success' => false, 'message' => 'Not your request'], 403);

        $agent = $request->user();
        $msgId = DB::table('request_messages')->insertGetId([
            'request_id'  => $id,
            'sender_id'   => $agentId,
            'sender_role' => 'agent',
            'message'     => $request->message,
            'is_read'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        try {
            $customer = DB::table('users')->where('id', $req->customer_user_id)->first();
            if ($customer?->fcm_token) {
                FcmService::send($customer->fcm_token, "Message from Agent {$agent->name}",
                    $request->message,
                    ['type' => 'request_message', 'request_id' => (string) $id]);
            }
        } catch (\Throwable $e) {}

        return response()->json(['success' => true, 'data' => ['id' => $msgId]]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formatMini($p): array
    {
        $imgs = is_string($p->images) ? (json_decode($p->images, true) ?? []) : ($p->images ?? []);
        return [
            'id'            => $p->id,
            'title'         => $p->title,
            'type'          => $p->type,
            'district_name' => $p->district_name,
            'monthly_rent'  => (float)$p->monthly_rent,
            'thumbnail'     => !empty($imgs) ? url('/api/v1/media?f=properties/' . basename($imgs[0])) : null,
            'is_available'  => (bool)$p->is_available,
            'is_booked'     => (bool)$p->is_booked,
            'created_at'    => $p->created_at,
        ];
    }

    private function formatFull($p): array
    {
        $imgs      = is_string($p->images)    ? (json_decode($p->images,    true) ?? []) : ($p->images    ?? []);
        $amenities = is_string($p->amenities) ? (json_decode($p->amenities, true) ?? []) : ($p->amenities ?? []);
        return [
            'id'            => $p->id,
            'title'         => $p->title,
            'description'   => $p->description,
            'type'          => $p->type,
            'address'       => $p->address,
            'district_id'   => $p->district_id,
            'district_name' => $p->district_name,
            'bedrooms'      => (int)$p->bedrooms,
            'bathrooms'     => (int)$p->bathrooms,
            'kitchens'      => (int)($p->kitchens ?? 0),
            'living_rooms'  => (int)($p->living_rooms ?? 0),
            'floor'         => $p->floor,
            'area_sqm'      => $p->area_sqm ? (float)$p->area_sqm : null,
            'furnishing'    => $p->furnishing ?? 'unfurnished',
            'monthly_rent'  => (float)$p->monthly_rent,
            'deposit'       => (float)($p->deposit ?? 0),
            'brokerage_fee' => (float)($p->brokerage_fee ?? 0),
            'amenities'     => is_array($amenities) ? $amenities : [],
            'images'        => array_map(fn($img) => url('/api/v1/media?f=properties/' . basename($img)), $imgs),
            'thumbnail'     => !empty($imgs) ? url('/api/v1/media?f=properties/' . basename($imgs[0])) : null,
            'is_available'  => (bool)$p->is_available,
            'is_booked'     => (bool)$p->is_booked,
            'created_at'    => $p->created_at,
            'updated_at'    => $p->updated_at,
        ];
    }
}
