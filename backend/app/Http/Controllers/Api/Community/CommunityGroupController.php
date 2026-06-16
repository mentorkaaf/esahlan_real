<?php
namespace App\Http\Controllers\Api\Community;
use App\Http\Controllers\Controller;
use App\Models\CommunityGroup;
use App\Models\CommunityGroupMember;
use App\Models\CommunityPost;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CommunityGroupController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->get('type','suggested'); // suggested|mine|discover
        $userId = auth()->id();

        if ($type === 'mine') {
            $groups = CommunityGroup::whereHas('members', fn($q) => $q->where('user_id',$userId)->where('status','active'))->withCount('members')->latest()->paginate(20);
        } elseif ($type === 'discover') {
            $joined = CommunityGroup::whereHas('members', fn($q) => $q->where('user_id',$userId))->pluck('id');
            $groups = CommunityGroup::whereNotIn('id',$joined)->where('privacy','public')->withCount('members')->orderByDesc('members_count')->paginate(20);
        } else {
            $groups = CommunityGroup::where('privacy','public')->withCount('members')->orderByDesc('members_count')->paginate(20);
        }

        return response()->json(['status'=>'success','data'=>$groups->map(fn($g) => $this->transformGroup($g, $userId))->toArray(),'meta'=>['current_page'=>$groups->currentPage(),'last_page'=>$groups->lastPage()]]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'privacy' => 'in:public,private',
            'category' => 'in:district,business,university,travel,food,general',
            'location' => 'nullable|string|max:255',
            'cover_photo' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
        ]);

        $coverUrl = null;
        if ($request->hasFile('cover_photo')) {
            $path = $request->file('cover_photo')->store('community/groups','public');
            $coverUrl = cdn_url($path);
        }

        $group = CommunityGroup::create([
            'owner_id' => auth()->id(),
            'name' => $request->name,
            'slug' => Str::slug($request->name).'-'.Str::random(6),
            'description' => $request->description,
            'privacy' => $request->privacy ?? 'public',
            'category' => $request->category ?? 'general',
            'location' => $request->location,
            'cover_photo' => $coverUrl,
        ]);

        CommunityGroupMember::create(['group_id'=>$group->id,'user_id'=>auth()->id(),'role'=>'owner','status'=>'active']);

        return response()->json(['status'=>'success','data'=>$this->transformGroup($group, auth()->id())], 201);
    }

    public function show(int $id)
    {
        $group = CommunityGroup::with('owner.communityProfile')->findOrFail($id);
        return response()->json(['status'=>'success','data'=>$this->transformGroup($group, auth()->id())]);
    }

    public function join(int $id)
    {
        $group = CommunityGroup::findOrFail($id);
        $userId = auth()->id();
        $existing = CommunityGroupMember::where('group_id',$id)->where('user_id',$userId)->first();

        if ($existing) return response()->json(['status'=>'error','message'=>'Already a member'],422);

        $status = $group->approval_required ? 'pending' : 'active';
        CommunityGroupMember::create(['group_id'=>$id,'user_id'=>$userId,'role'=>'member','status'=>$status]);
        if ($status === 'active') $group->increment('members_count');

        return response()->json(['status'=>'success','joined'=>$status==='active','pending'=>$status==='pending']);
    }

    public function leave(int $id)
    {
        $member = CommunityGroupMember::where('group_id',$id)->where('user_id',auth()->id())->firstOrFail();
        if ($member->role === 'owner') return response()->json(['status'=>'error','message'=>'Owner cannot leave'],422);
        if ($member->status === 'active') CommunityGroup::find($id)?->decrement('members_count');
        $member->delete();
        return response()->json(['status'=>'success','message'=>'Left group']);
    }

    public function posts(int $id, Request $request)
    {
        $group = CommunityGroup::findOrFail($id);
        $userId = auth()->id();
        $isMember = CommunityGroupMember::where('group_id',$id)->where('user_id',$userId)->where('status','active')->exists();
        if ($group->privacy === 'private' && !$isMember) return response()->json(['status'=>'error','message'=>'Private group'],403);

        $posts = CommunityPost::with(['user.communityProfile','media','userReaction'])
            ->where('group_id',$id)->latest()->paginate(15);
        $feed = new CommunityFeedController();
        return response()->json(['status'=>'success','data'=>$posts->map(fn($p)=>$feed->transformPost($p,$userId))->toArray(),'meta'=>['current_page'=>$posts->currentPage(),'last_page'=>$posts->lastPage()]]);
    }

    private function transformGroup($group, int $userId): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'slug' => $group->slug,
            'description' => $group->description,
            'cover_photo' => $group->cover_photo,
            'avatar' => $group->avatar,
            'privacy' => $group->privacy,
            'category' => $group->category,
            'location' => $group->location,
            'members_count' => $group->members_count,
            'posts_count' => $group->posts_count,
            'is_member' => CommunityGroupMember::where('group_id',$group->id)->where('user_id',$userId)->where('status','active')->exists(),
            'membership_status' => CommunityGroupMember::where('group_id',$group->id)->where('user_id',$userId)->value('status'),
            'is_owner' => $group->owner_id === $userId,
        ];
    }
}