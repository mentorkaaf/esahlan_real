<?php
namespace App\Http\Controllers\Api\Community;
use App\Http\Controllers\Controller;
use App\Models\CommunityProfile;
use App\Models\CommunityPost;
use App\Models\CommunityFollow;
use App\Models\User;
use Illuminate\Http\Request;

class CommunityProfileController extends Controller
{
    public function show(int $userId)
    {
        $user = User::with('communityProfile')->findOrFail($userId);
        $feed = new CommunityFeedController();
        return response()->json(['status'=>'success','data'=>$feed->transformUser($user, auth()->id())]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'display_name' => 'nullable|string|max:100',
            'username' => 'nullable|string|max:50|unique:community_profiles,username,'.auth()->user()->communityProfile?->id,
            'bio' => 'nullable|string|max:500',
            'website' => 'nullable|string|max:255',
            'privacy' => 'nullable|in:public,friends,private',
            'is_business' => 'boolean',
            'business_category' => 'nullable|string|max:100',
            'cover_photo' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
        ]);

        $profile = CommunityProfile::firstOrCreate(['user_id'=>auth()->id()]);
        $data = $request->only(['display_name','username','bio','website','privacy','is_business','business_category']);

        if ($request->hasFile('cover_photo')) {
            $path = $request->file('cover_photo')->store('community/covers','public');
            $data['cover_photo'] = $path;
        }

        $profile->update($data);
        return response()->json(['status'=>'success','data'=>$profile]);
    }

    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'cover_photo' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
        ]);

        $user = auth()->user();
        $profile = \App\Models\CommunityProfile::firstOrCreate(['user_id' => $user->id]);
        $data = [];

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('community/avatars', 'public');
            $url = cdn_url($path);
            // Update user's avatar
            $user->update(['avatar' => $url]);
            $data['avatar'] = $url;
        }

        if ($request->hasFile('cover_photo')) {
            $path = $request->file('cover_photo')->store('community/covers', 'public');
            $data['cover_photo'] = $path;
        }

        if (!empty($data)) $profile->update($data);

        return response()->json(['status' => 'success', 'data' => [
            'avatar' => cdn_url($user->fresh()->avatar),
            'cover_photo' => cdn_url($profile->fresh()->getRawOriginal('cover_photo')),
        ]]);
    }

    public function onboarding(Request $request)
    {
        $request->validate([
            'country'       => 'required|string|max:100',
            'city'          => 'required|string|max:100',
            'gender'        => 'required|in:male,female,other',
            'date_of_birth' => 'required|date|before:today',
            'interests'     => 'required|array|min:1',
            'interests.*'   => 'string|max:50',
        ]);

        $profile = CommunityProfile::firstOrCreate(['user_id' => auth()->id()]);
        $profile->update([
            'country'              => $request->country,
            'city'                 => $request->city,
            'gender'               => $request->gender,
            'date_of_birth'        => $request->date_of_birth,
            'interests'            => $request->interests,
            'onboarding_completed' => true,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Profile updated']);
    }

    public function checkOnboarding()
    {
        $profile = CommunityProfile::where('user_id', auth()->id())->first();
        return response()->json(['status' => 'success', 'completed' => $profile?->onboarding_completed ?? false]);
    }

    public function posts(int $userId, Request $request)
    {
        $type = $request->get('type','posts');
        $authId = auth()->id();
        $feed = new CommunityFeedController();

        $query = CommunityPost::with(['user.communityProfile','media','userReaction'])
            ->where('user_id',$userId)
            ->where('privacy','public');

        if ($type === 'reels') $query->whereIn('type',['reel','video']);
        elseif ($type === 'photos') $query->whereIn('type',['image']);
        else $query->whereNotIn('type',['reel']);

        $posts = $query->latest()->paginate(12);
        return response()->json(['status'=>'success','data'=>$posts->map(fn($p)=>$feed->transformPost($p,$authId))->toArray(),'meta'=>['current_page'=>$posts->currentPage(),'last_page'=>$posts->lastPage()]]);
    }

    public function followers(int $userId)
    {
        $follows = CommunityFollow::with('follower.communityProfile')->where('following_id',$userId)->latest()->paginate(20);
        $feed = new CommunityFeedController();
        $authId = auth()->id();
        return response()->json(['status'=>'success','data'=>$follows->map(fn($f)=>$feed->transformUser($f->follower,$authId))->toArray()]);
    }

    public function following(int $userId)
    {
        $follows = CommunityFollow::with('following.communityProfile')->where('follower_id',$userId)->latest()->paginate(20);
        $feed = new CommunityFeedController();
        $authId = auth()->id();
        return response()->json(['status'=>'success','data'=>$follows->map(fn($f)=>$feed->transformUser($f->following,$authId))->toArray()]);
    }
}