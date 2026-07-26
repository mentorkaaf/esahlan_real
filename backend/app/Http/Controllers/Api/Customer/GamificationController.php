<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Services\GamificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GamificationController extends Controller
{
    // GET /gamification — streak + badges for current user
    public function profile(Request $request)
    {
        $data = GamificationService::profileData($request->user()->id);
        return response()->json(['success' => true, 'data' => $data]);
    }

    // GET /gamification/leaderboard?period=weekly|monthly|alltime
    public function leaderboard(Request $request)
    {
        $period  = $request->query('period', 'weekly');
        $board   = GamificationService::leaderboard($period, 50);
        $userId  = $request->user()->id;
        $myRank  = null;

        $board = array_map(function (array $row) use ($userId, &$myRank) {
            if (($row['_user_id'] ?? null) === $userId) {
                $row['is_me'] = true;
                $myRank = $row['rank'];
            }
            unset($row['_user_id']);
            return $row;
        }, $board);

        return response()->json([
            'success' => true,
            'data'    => ['period' => $period, 'board' => $board, 'my_rank' => $myRank],
        ]);
    }
}
