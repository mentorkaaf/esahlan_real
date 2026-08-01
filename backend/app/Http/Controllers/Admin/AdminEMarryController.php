<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminEMarryController extends Controller
{
    // ── GET /admin/emarry ─────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $status = $request->get('status', 'pending');

        $profiles = DB::table('emarry_profiles as p')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->when($status !== 'all', fn($q) => $q->where('p.status', $status))
            ->select(
                'p.id', 'p.user_id', 'p.gender', 'p.looking_for', 'p.age',
                'p.city', 'p.education', 'p.occupation', 'p.marital_status',
                'p.has_children', 'p.bio', 'p.photos', 'p.status',
                'p.fee_paid', 'p.is_visible', 'p.created_at', 'p.approved_at',
                'u.name', 'u.email', 'u.phone', 'u.avatar'
            )
            ->orderByDesc('p.created_at')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'pending'  => DB::table('emarry_profiles')->where('status', 'pending')->count(),
            'approved' => DB::table('emarry_profiles')->where('status', 'approved')->count(),
            'rejected' => DB::table('emarry_profiles')->where('status', 'rejected')->count(),
            'interests'=> DB::table('emarry_interests')->count(),
            'matched'  => DB::table('emarry_interests')->where('status', 'accepted')->count(),
        ];

        return view('admin.emarry.index', compact('profiles', 'stats', 'status'));
    }

    // ── POST /admin/emarry/{id}/approve ──────────────────────────────────────
    public function approve(Request $request, int $id)
    {
        $profile = DB::table('emarry_profiles')->where('id', $id)->first();
        if (!$profile) return back()->with('error', 'Profile not found');

        DB::table('emarry_profiles')->where('id', $id)->update([
            'status'      => 'approved',
            'is_visible'  => true,
            'approved_at' => now(),
            'updated_at'  => now(),
        ]);

        // Notify user
        try {
            $user = DB::table('users')->where('id', $profile->user_id)->first();
            if ($user?->fcm_token) {
                FcmService::sendToToken(
                    $user->fcm_token,
                    '💍 eMarry Profile Approved!',
                    'Your profile is now live. Start browsing potential matches!',
                    ['type' => 'emarry_approved', 'deep_link' => '/community?tab=emarry']
                );
            }
        } catch (\Throwable) {}

        return back()->with('success', 'Profile approved and is now visible.');
    }

    // ── POST /admin/emarry/{id}/reject ────────────────────────────────────────
    public function reject(Request $request, int $id)
    {
        $request->validate(['reason' => 'nullable|string|max:500']);
        $profile = DB::table('emarry_profiles')->where('id', $id)->first();
        if (!$profile) return back()->with('error', 'Profile not found');

        DB::table('emarry_profiles')->where('id', $id)->update([
            'status'     => 'rejected',
            'is_visible' => false,
            'updated_at' => now(),
        ]);

        try {
            $user = DB::table('users')->where('id', $profile->user_id)->first();
            if ($user?->fcm_token) {
                $reason = $request->reason
                    ? "Reason: {$request->reason}"
                    : 'Please review our community guidelines and resubmit.';
                FcmService::sendToToken(
                    $user->fcm_token,
                    'eMarry Profile Review',
                    "Your profile needs changes. $reason",
                    ['type' => 'emarry_rejected', 'deep_link' => '/community?tab=emarry']
                );
            }
        } catch (\Throwable) {}

        return back()->with('success', 'Profile rejected.');
    }

    // ── POST /admin/emarry/{id}/delete ────────────────────────────────────────
    public function delete(int $id)
    {
        DB::table('emarry_profiles')->where('id', $id)->delete();
        return back()->with('success', 'Profile deleted.');
    }

    // ── GET /admin/emarry/{id}/detail ─────────────────────────────────────────
    public function detail(int $id)
    {
        $profile = DB::table('emarry_profiles as p')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->where('p.id', $id)
            ->select('p.*', 'u.name', 'u.email', 'u.phone', 'u.avatar', 'u.created_at as joined_at')
            ->first();

        if (!$profile) abort(404);

        $interests_sent = DB::table('emarry_interests as i')
            ->join('users as u', 'u.id', '=', 'i.receiver_id')
            ->where('i.sender_id', $profile->user_id)
            ->select('i.*', 'u.name as receiver_name')
            ->orderByDesc('i.created_at')
            ->limit(20)
            ->get();

        $interests_received = DB::table('emarry_interests as i')
            ->join('users as u', 'u.id', '=', 'i.sender_id')
            ->where('i.receiver_id', $profile->user_id)
            ->select('i.*', 'u.name as sender_name')
            ->orderByDesc('i.created_at')
            ->limit(20)
            ->get();

        return view('admin.emarry.detail', compact('profile', 'interests_sent', 'interests_received'));
    }

    // ── GET /admin/emarry/interests ───────────────────────────────────────────
    public function interests(Request $request)
    {
        $interests = DB::table('emarry_interests as i')
            ->join('users as s', 's.id', '=', 'i.sender_id')
            ->join('users as r', 'r.id', '=', 'i.receiver_id')
            ->select(
                'i.id', 'i.status', 'i.message', 'i.created_at',
                's.name as sender_name', 's.id as sender_id',
                'r.name as receiver_name', 'r.id as receiver_id'
            )
            ->when($request->status, fn($q) => $q->where('i.status', $request->status))
            ->orderByDesc('i.created_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.emarry.interests', compact('interests'));
    }
}
