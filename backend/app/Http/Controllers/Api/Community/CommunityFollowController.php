<?php
namespace App\Http\Controllers\Api\Community;
use App\Http\Controllers\Controller;
use App\Models\CommunityFollow;
use App\Models\CommunityProfile;
use App\Models\CommunityNotification;
use Illuminate\Http\Request;

class CommunityFollowController extends Controller
{
    public function toggle(int $userId)
    {
        $me = auth()->id();
        if ($me === $userId) return response()->json(['status'=>'error','message'=>'Cannot follow yourself'],422);

        $existing = CommunityFollow::where('follower_id',$me)->where('following_id',$userId)->first();
        if ($existing) {
            $existing->delete();
            CommunityProfile::where('user_id',$me)->decrement('following_count');
            CommunityProfile::where('user_id',$userId)->decrement('followers_count');
            return response()->json(['status'=>'success','following'=>false]);
        }

        CommunityFollow::create(['follower_id'=>$me,'following_id'=>$userId]);
        CommunityProfile::firstOrCreate(['user_id'=>$me])->increment('following_count');
        CommunityProfile::firstOrCreate(['user_id'=>$userId])->increment('followers_count');
        CommunityNotification::create(['user_id'=>$userId,'actor_id'=>$me,'type'=>'follow','notifiable_type'=>'user','notifiable_id'=>$userId]);

        return response()->json(['status'=>'success','following'=>true]);
    }
}