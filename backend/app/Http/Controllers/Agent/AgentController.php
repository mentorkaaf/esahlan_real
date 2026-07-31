<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
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

        return response()->json([
            'success' => true,
            'data'    => [
                'stats' => [
                    'total_listings'   => $total,
                    'active_listings'  => $active,
                    'rented'           => $rented,
                    'booked'           => $booked,
                    'total_earned'     => (float)$totalEarned,
                    'month_earned'     => (float)$monthEarned,
                    'wallet_balance'   => (float)$wallet->balance,
                ],
                'recent_properties'   => $recent,
                'recent_commissions'  => $recentCommissions,
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
        $agentDistrictId = $request->user()->district_id;

        $query = DB::table('house_requests')
            ->join('users as customers', 'house_requests.customer_user_id', '=', 'customers.id')
            ->leftJoin('districts', 'house_requests.district_id', '=', 'districts.id')
            ->where('house_requests.status', 'open')
            ->select([
                'house_requests.id', 'house_requests.type', 'house_requests.bedrooms',
                'house_requests.budget_min', 'house_requests.budget_max',
                'house_requests.description', 'house_requests.status',
                'house_requests.created_at',
                'customers.name as customer_name', 'customers.phone as customer_phone',
                'districts.name as district_name',
            ]);

        // Show requests in agent's district first, then others
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

    public function contactRequest(Request $request, $id)
    {
        $updated = DB::table('house_requests')
            ->where('id', $id)
            ->where('status', 'open')
            ->update(['status' => 'contacted', 'updated_at' => now()]);

        return response()->json([
            'success' => $updated > 0,
            'message' => $updated > 0 ? 'Marked as contacted' : 'Already handled',
        ]);
    }

    public function closeRequest(Request $request, $id)
    {
        DB::table('house_requests')
            ->where('id', $id)
            ->whereIn('status', ['open', 'contacted'])
            ->update(['status' => 'closed', 'updated_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Request closed']);
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
