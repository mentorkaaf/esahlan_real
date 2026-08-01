<?php

namespace App\Http\Controllers\Api\Community;

use App\Events\EMarryInterestAccepted;
use App\Events\EMarryInterestSent;
use App\Http\Controllers\Controller;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class EMarryController extends Controller
{
    // ── GET /emarry/profiles  ─────────────────────────────────────────────────
    // Browse approved profiles matching the user's looking_for preference
    public function index(Request $request)
    {
        $user    = $request->user();
        $myProfile = DB::table('emarry_profiles')->where('user_id', $user->id)->first();

        $query = DB::table('emarry_profiles as p')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->where('p.status', 'approved')
            ->where('p.is_visible', true)
            ->where('p.user_id', '!=', $user->id);

        // Filter by what I'm looking for (if my profile exists)
        if ($myProfile) {
            $query->where('p.gender', $myProfile->looking_for);
        }

        // District filter
        if ($request->district_id) {
            $query->where('p.district_id', $request->district_id);
        }

        // Age range
        if ($request->min_age) $query->where('p.age', '>=', $request->min_age);
        if ($request->max_age) $query->where('p.age', '<=', $request->max_age);

        $profiles = $query->select(
            'p.id', 'p.user_id', 'p.gender', 'p.age', 'p.city',
            'p.nationality', 'p.education', 'p.occupation',
            'p.has_children', 'p.marital_status', 'p.bio', 'p.photos',
            'u.name', 'u.avatar'
        )
        ->orderByDesc('p.approved_at')
        ->paginate(20);

        // Attach interest status for each profile
        $sentIds = DB::table('emarry_interests')
            ->where('sender_id', $user->id)
            ->pluck('status', 'receiver_id');

        $items = collect($profiles->items())->map(function ($p) use ($sentIds) {
            $p->photos = json_decode($p->photos ?? '[]');
            $p->interest_status = $sentIds[$p->user_id] ?? null;
            return $p;
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'data'      => $items,
                'total'     => $profiles->total(),
                'last_page' => $profiles->lastPage(),
                'my_profile'=> $myProfile ? $this->_formatProfile($myProfile) : null,
            ],
        ]);
    }

    // ── GET /emarry/profile/me  ───────────────────────────────────────────────
    public function myProfile(Request $request)
    {
        $p = DB::table('emarry_profiles')->where('user_id', $request->user()->id)->first();
        return response()->json([
            'success' => true,
            'data'    => $p ? $this->_formatProfile($p) : null,
        ]);
    }

    // ── POST /emarry/profile  ─────────────────────────────────────────────────
    // Create or update own profile (status resets to pending on update)
    public function saveProfile(Request $request)
    {
        $v = Validator::make($request->all(), [
            'gender'         => 'required|in:male,female',
            'looking_for'    => 'required|in:male,female',
            'age'            => 'required|integer|min:18|max:80',
            'city'           => 'nullable|string|max:100',
            'district_id'    => 'nullable|integer|exists:districts,id',
            'nationality'    => 'nullable|string|max:100',
            'education'      => 'nullable|in:high_school,bachelor,master,phd,other',
            'occupation'     => 'nullable|string|max:100',
            'has_children'   => 'nullable|boolean',
            'marital_status' => 'nullable|in:single,divorced,widowed',
            'bio'            => 'nullable|string|max:500',
            'photos'         => 'nullable|array|max:4',
            'photos.*'       => 'nullable|string',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user = $request->user();

        $existing = DB::table('emarry_profiles')->where('user_id', $user->id)->first();

        $data = [
            'gender'         => $request->gender,
            'looking_for'    => $request->looking_for,
            'age'            => $request->age,
            'city'           => $request->city,
            'district_id'    => $request->district_id,
            'nationality'    => $request->nationality,
            'education'      => $request->education,
            'occupation'     => $request->occupation,
            'has_children'   => $request->boolean('has_children'),
            'marital_status' => $request->marital_status ?? 'single',
            'bio'            => $request->bio,
            'photos'         => json_encode($request->photos ?? []),
            'updated_at'     => now(),
        ];

        if ($existing) {
            // Reset to pending on update so admin re-reviews
            $data['status']     = 'pending';
            $data['is_visible'] = false;
            DB::table('emarry_profiles')->where('user_id', $user->id)->update($data);
        } else {
            $data['user_id']    = $user->id;
            $data['status']     = 'pending';
            $data['is_visible'] = false;
            $data['created_at'] = now();
            DB::table('emarry_profiles')->insert($data);
        }

        return response()->json(['success' => true, 'message' => 'Profile submitted for review.']);
    }

    // ── POST /emarry/interest/{userId}  ──────────────────────────────────────
    public function sendInterest(Request $request, int $userId)
    {
        $v = Validator::make($request->all(), ['message' => 'nullable|string|max:300']);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $sender   = $request->user();
        if ($sender->id === $userId) {
            return response()->json(['success' => false, 'message' => 'Cannot send interest to yourself'], 422);
        }

        // Check receiver has approved profile
        $receiver = DB::table('emarry_profiles')
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->where('is_visible', true)
            ->first();

        if (!$receiver) return response()->json(['success' => false, 'message' => 'Profile not found'], 404);

        // Upsert interest
        $existing = DB::table('emarry_interests')
            ->where('sender_id', $sender->id)
            ->where('receiver_id', $userId)
            ->first();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Interest already sent'], 422);
        }

        DB::table('emarry_interests')->insert([
            'sender_id'   => $sender->id,
            'receiver_id' => $userId,
            'status'      => 'pending',
            'message'     => $request->message,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Realtime + FCM notify receiver
        try {
            broadcast(new EMarryInterestSent($userId, $sender->name, $sender->id));
        } catch (\Throwable) {}
        try {
            $receiverUser = DB::table('users')->where('id', $userId)->first();
            if ($receiverUser?->fcm_token) {
                FcmService::sendToToken(
                    $receiverUser->fcm_token,
                    '💍 New Interest!',
                    "{$sender->name} is interested in you",
                    ['type' => 'emarry_interest', 'deep_link' => '/community?tab=emarry']
                );
            }
        } catch (\Throwable) {}

        return response()->json(['success' => true, 'message' => 'Interest sent!']);
    }

    // ── POST /emarry/interest/{senderId}/respond  ─────────────────────────────
    public function respondInterest(Request $request, int $senderId)
    {
        $v = Validator::make($request->all(), ['action' => 'required|in:accept,reject']);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user = $request->user();
        $updated = DB::table('emarry_interests')
            ->where('sender_id', $senderId)
            ->where('receiver_id', $user->id)
            ->where('status', 'pending')
            ->update([
                'status'     => $request->action === 'accept' ? 'accepted' : 'rejected',
                'updated_at' => now(),
            ]);

        if (!$updated) return response()->json(['success' => false, 'message' => 'Interest not found'], 404);

        if ($request->action === 'accept') {
            // Realtime + FCM notify original sender
            try {
                broadcast(new EMarryInterestAccepted($senderId, $user->name, $user->id));
            } catch (\Throwable) {}
            try {
                $senderUser = DB::table('users')->where('id', $senderId)->first();
                if ($senderUser?->fcm_token) {
                    FcmService::sendToToken(
                        $senderUser->fcm_token,
                        '💍 Interest Accepted!',
                        "{$user->name} accepted your interest",
                        ['type' => 'emarry_accepted', 'deep_link' => '/community?tab=emarry']
                    );
                }
            } catch (\Throwable) {}
        }

        return response()->json(['success' => true, 'message' => 'Response sent.']);
    }

    // ── GET /emarry/interests/received  ──────────────────────────────────────
    public function receivedInterests(Request $request)
    {
        $user = $request->user();
        $interests = DB::table('emarry_interests as i')
            ->join('users as u', 'u.id', '=', 'i.sender_id')
            ->join('emarry_profiles as p', 'p.user_id', '=', 'i.sender_id')
            ->where('i.receiver_id', $user->id)
            ->select('i.id', 'i.status', 'i.message', 'i.created_at',
                     'u.id as user_id', 'u.name', 'u.avatar',
                     'p.age', 'p.city', 'p.gender', 'p.photos')
            ->orderByDesc('i.created_at')
            ->get()
            ->map(function ($r) {
                $r->photos = json_decode($r->photos ?? '[]');
                return $r;
            });

        return response()->json(['success' => true, 'data' => $interests]);
    }

    // ── POST /emarry/photo  ───────────────────────────────────────────────────
    public function uploadPhoto(Request $request)
    {
        $v = Validator::make($request->all(), ['photo' => 'required|image|max:4096']);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $path = $request->file('photo')->store('emarry/photos', 'public');
        $url  = Storage::url($path);

        return response()->json(['success' => true, 'url' => $url]);
    }

    private function _formatProfile(object $p): array
    {
        return [
            'id'             => $p->id,
            'user_id'        => $p->user_id,
            'gender'         => $p->gender,
            'looking_for'    => $p->looking_for,
            'age'            => $p->age,
            'city'           => $p->city,
            'nationality'    => $p->nationality,
            'education'      => $p->education,
            'occupation'     => $p->occupation,
            'has_children'   => (bool) $p->has_children,
            'marital_status' => $p->marital_status,
            'bio'            => $p->bio,
            'photos'         => json_decode($p->photos ?? '[]'),
            'status'         => $p->status,
            'is_visible'     => (bool) $p->is_visible,
        ];
    }
}
