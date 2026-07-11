<?php
namespace App\Http\Controllers\Api\Community;
use App\Http\Controllers\Controller;
use App\Models\CommunityReport;
use App\Models\CommunityBlockedUser;
use Illuminate\Http\Request;

class CommunityReportController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'reportable_type' => 'required|in:post,comment,user,story',
            'reportable_id' => 'required|integer',
            'reason' => 'required|in:spam,hate,violence,nudity,misinformation,other',
            'description' => 'nullable|string|max:500',
        ]);

        CommunityReport::create([
            'reporter_id' => auth()->id(),
            'reportable_type' => $request->reportable_type,
            'reportable_id' => $request->reportable_id,
            'reason' => $request->reason,
            'description' => $request->description,
        ]);

        return response()->json(['status'=>'success','message'=>'Report submitted']);
    }

    public function block(int $userId)
    {
        $me = auth()->id();
        if ($userId === $me) return response()->json(['status'=>'error'],422);

        $existing = \Illuminate\Support\Facades\DB::table('community_blocks')
            ->where('blocker_id', $me)->where('blocked_id', $userId)->first();
        if ($existing) {
            \Illuminate\Support\Facades\DB::table('community_blocks')
                ->where('blocker_id', $me)->where('blocked_id', $userId)->delete();
            return response()->json(['status'=>'success','blocked'=>false]);
        }
        \Illuminate\Support\Facades\DB::table('community_blocks')->insert([
            'blocker_id' => $me, 'blocked_id' => $userId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['status'=>'success','blocked'=>true]);
    }
}