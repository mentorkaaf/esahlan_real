<?php

namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class CommunityBlockController extends Controller
{
    public function toggle(int $userId)
    {
        $me = auth()->id();
        if ($userId === $me) {
            return response()->json(['status' => 'error', 'message' => 'Cannot block yourself'], 422);
        }

        $existing = DB::table('community_blocks')
            ->where('blocker_id', $me)
            ->where('blocked_id', $userId)
            ->first();

        if ($existing) {
            DB::table('community_blocks')
                ->where('blocker_id', $me)
                ->where('blocked_id', $userId)
                ->delete();

            return response()->json(['status' => 'success', 'blocked' => false]);
        }

        // Block: remove follows in both directions
        DB::table('community_follows')
            ->where(function ($q) use ($me, $userId) {
                $q->where('follower_id', $me)->where('following_id', $userId);
            })
            ->orWhere(function ($q) use ($me, $userId) {
                $q->where('follower_id', $userId)->where('following_id', $me);
            })
            ->delete();

        // Remove pending follow requests (community_follows with status=pending)
        DB::table('community_follows')
            ->where('status', 'pending')
            ->where(function ($q) use ($me, $userId) {
                $q->where(function ($q2) use ($me, $userId) {
                    $q2->where('follower_id', $me)->where('following_id', $userId);
                })->orWhere(function ($q2) use ($me, $userId) {
                    $q2->where('follower_id', $userId)->where('following_id', $me);
                });
            })
            ->delete();

        DB::table('community_blocks')->insert([
            'blocker_id' => $me,
            'blocked_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'success', 'blocked' => true]);
    }

    public function toggleMute(int $userId)
    {
        $me = auth()->id();
        if ($userId === $me) {
            return response()->json(['status' => 'error', 'message' => 'Cannot mute yourself'], 422);
        }

        $existing = DB::table('community_mutes')
            ->where('muter_id', $me)
            ->where('muted_id', $userId)
            ->first();

        if ($existing) {
            DB::table('community_mutes')
                ->where('muter_id', $me)
                ->where('muted_id', $userId)
                ->delete();

            return response()->json(['status' => 'success', 'muted' => false]);
        }

        DB::table('community_mutes')->insert([
            'muter_id'   => $me,
            'muted_id'   => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'success', 'muted' => true]);
    }

    public function checkBlock(int $userId)
    {
        $me = auth()->id();

        $isBlocked = DB::table('community_blocks')
            ->where('blocker_id', $me)
            ->where('blocked_id', $userId)
            ->exists();

        $isBlockedBy = DB::table('community_blocks')
            ->where('blocker_id', $userId)
            ->where('blocked_id', $me)
            ->exists();

        return response()->json([
            'status'         => 'success',
            'is_blocked'     => $isBlocked,
            'is_blocked_by'  => $isBlockedBy,
        ]);
    }
}
