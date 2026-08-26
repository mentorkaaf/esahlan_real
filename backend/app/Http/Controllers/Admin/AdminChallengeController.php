<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminChallengeController extends Controller {
    public function index() {
        $ch=DB::table('driver_challenges')->orderByDesc('created_at')->paginate(20);
        $ch->getCollection()->transform(function($c){
            $c->completions=DB::table('driver_challenge_progress')->where('challenge_id',$c->id)->whereNotNull('completed_at')->count();
            return $c;
        });
        return response()->json(['success'=>true,'data'=>$ch]);
    }
    public function store(Request $request) {
        $v=$request->validate(['title'=>'required|string|max:150','description'=>'nullable|string',
            'type'=>'required|in:delivery_count','target_count'=>'required|integer|min:1',
            'reward_amount'=>'required|numeric|min:0','starts_at'=>'nullable|date',
            'ends_at'=>'nullable|date','module_slug'=>'nullable|string']);
        DB::table('driver_challenges')->insert(array_merge($v,['is_active'=>true,'created_at'=>now(),'updated_at'=>now()]));
        return response()->json(['success'=>true,'message'=>'Challenge created']);
    }
    public function toggle(int $id) {
        $ch=DB::table('driver_challenges')->find($id);
        if(!$ch) return response()->json(['success'=>false],404);
        DB::table('driver_challenges')->where('id',$id)->update(['is_active'=>!$ch->is_active]);
        return response()->json(['success'=>true]);
    }
    public function destroy(int $id) {
        DB::table('driver_challenge_progress')->where('challenge_id',$id)->delete();
        DB::table('driver_challenges')->where('id',$id)->delete();
        return response()->json(['success'=>true]);
    }
}
