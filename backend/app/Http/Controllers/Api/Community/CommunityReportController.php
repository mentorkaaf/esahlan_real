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
        if ($userId === auth()->id()) return response()->json(['status'=>'error'],422);
        $existing = \App\Models\CommunityBlockedUser::where('user_id',auth()->id())->where('blocked_user_id',$userId)->first();
        if ($existing) {
            $existing->delete();
            return response()->json(['status'=>'success','blocked'=>false]);
        }
        \App\Models\CommunityBlockedUser::create(['user_id'=>auth()->id(),'blocked_user_id'=>$userId]);
        return response()->json(['status'=>'success','blocked'=>true]);
    }
}