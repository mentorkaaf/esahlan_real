<?php
namespace App\Http\Controllers\Api\Community;
use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\CommunityFollow;
use App\Models\CommunityProfile;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\Community\CommunityAdController;

class CommunityFeedController extends Controller
{
    // Main feed — all public posts (everyone sees everything)
    public function following(Request $request)
    {
        $userId = auth()->id();

        $posts = CommunityPost::with(['user.communityProfile','media','userReaction','page'])
            ->whereNull('group_id')
            ->where('privacy', '!=', 'private')
            ->inRandomOrder()
            ->paginate(15);

        $transformed = $this->transformPosts($posts, $userId);

        // Inject ads every 5 posts
        if ($posts->currentPage() <= 3) {
            $ads = CommunityAdController::getAdsForPlacement('feed', $userId, 2);
            foreach ($ads as $i => $ad) {
                $pos = min(($i + 1) * 4, count($transformed));
                array_splice($transformed, $pos, 0, [$ad]);
            }
        }

        return response()->json(['status'=>'success','data'=>$transformed,'meta'=>['current_page'=>$posts->currentPage(),'last_page'=>$posts->lastPage(),'total'=>$posts->total()]]);
    }

    // Trending feed — only high-engagement posts
    public function explore(Request $request)
    {
        $userId = auth()->id();
        $posts = CommunityPost::with(['user.communityProfile','media','userReaction','page'])
            ->where('privacy','public')
            ->whereNull('group_id')
            ->where(function ($q) {
                $q->where('likes_count', '>=', 1)
                  ->orWhere('comments_count', '>=', 1)
                  ->orWhere('views_count', '>=', 5)
                  ->orWhere('shares_count', '>=', 1);
            })
            ->orderByRaw('(likes_count * 3 + comments_count * 2 + shares_count * 4 + views_count) DESC')
            ->paginate(15);

        return response()->json(['status'=>'success','data'=>$this->transformPosts($posts, $userId),'meta'=>['current_page'=>$posts->currentPage(),'last_page'=>$posts->lastPage()]]);
    }

    // Reels feed
    public function reels(Request $request)
    {
        $userId = auth()->id();
        $posts = CommunityPost::with(['user.communityProfile','media','userReaction'])
            ->whereIn('type',['reel','video'])
            ->where('privacy','public')
            ->orderByDesc('views_count')
            ->orderByDesc('created_at')
            ->paginate(10);

        $transformed = $this->transformPosts($posts, $userId);

        // Inject reel ads
        if ($posts->currentPage() <= 2) {
            $ads = CommunityAdController::getAdsForPlacement('reels', $userId, 1);
            foreach ($ads as $ad) {
                $pos = min(3, count($transformed));
                array_splice($transformed, $pos, 0, [$ad]);
            }
        }

        return response()->json(['status'=>'success','data'=>$transformed,'meta'=>['current_page'=>$posts->currentPage(),'last_page'=>$posts->lastPage()]]);
    }

    // Trending hashtags
    public function trending()
    {
        $hashtags = \App\Models\CommunityHashtag::orderByDesc('posts_count')->take(20)->get();
        return response()->json(['status'=>'success','data'=>$hashtags]);
    }

    // Search
    public function search(Request $request)
    {
        $q = $request->get('q','');
        $type = $request->get('type','posts'); // posts|users|groups|hashtags
        $userId = auth()->id();

        if ($type === 'users') {
            $results = \App\Models\User::with('communityProfile')
                ->where('name','like',"%$q%")
                ->orWhere('phone','like',"%$q%")
                ->limit(20)->get()
                ->map(fn($u) => $this->transformUser($u, $userId));
            return response()->json(['status'=>'success','data'=>$results]);
        }

        if ($type === 'groups') {
            $results = \App\Models\CommunityGroup::where('name','like',"%$q%")->where('privacy','public')->limit(20)->get();
            return response()->json(['status'=>'success','data'=>$results]);
        }

        if ($type === 'hashtags') {
            $results = \App\Models\CommunityHashtag::where('name','like',"%$q%")->orderByDesc('posts_count')->limit(20)->get();
            return response()->json(['status'=>'success','data'=>$results]);
        }

        // Posts
        $posts = CommunityPost::with(['user.communityProfile','media','userReaction'])
            ->where('content','like',"%$q%")
            ->where('privacy','public')
            ->latest()->paginate(15);
        return response()->json(['status'=>'success','data'=>$this->transformPosts($posts, $userId)]);
    }

    // Stories feed
    public function stories()
    {
        $userId = auth()->id();
        $followingIds = CommunityFollow::where('follower_id', $userId)->pluck('following_id');
        $followingIds->push($userId);

        $users = \App\Models\User::with(['communityProfile','stories' => function($q) {
            $q->where('expires_at','>',now())->latest();
        }])
        ->whereIn('id', $followingIds)
        ->whereHas('stories', fn($q) => $q->where('expires_at','>',now()))
        ->get()
        ->map(function($u) use ($userId) {
            $stories = $u->stories->map(fn($s) => array_merge($s->toArray(), ['is_viewed' => $s->isViewedBy($userId)]));
            $allViewed = $stories->every(fn($s) => $s['is_viewed']);
            return ['user' => $this->transformUser($u, $userId), 'stories' => $stories, 'all_viewed' => $allViewed];
        });

        return response()->json(['status'=>'success','data'=>$users]);
    }

    // People — all community users
    public function suggestions()
    {
        $userId = auth()->id();
        $followingIds = CommunityFollow::where('follower_id', $userId)->pluck('following_id')->push($userId);

        $users = \App\Models\User::with('communityProfile')
            ->where('id', '!=', $userId)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn($u) => $this->transformUser($u, $userId));

        return response()->json(['status'=>'success','data'=>$users]);
    }

    private function transformPosts($posts, int $userId): array
    {
        return $posts->map(fn($p) => $this->transformPost($p, $userId))->toArray();
    }

    public function transformPost($post, int $userId): array
    {
        // Increment view count
        $post->increment('views_count');

        // If post belongs to a page, override user display with page info
        $displayUser = $this->transformUser($post->user, $userId);
        if ($post->page_id && $post->page) {
            $displayUser['name'] = $post->page->name;
            $displayUser['avatar'] = cdn_url($post->page->avatar) ?? $displayUser['avatar'];
            $displayUser['is_business'] = true;
        }

        // Shared post details
        $sharedPost = null;
        if ($post->shared_post_id && $post->sharedPost) {
            $sp = $post->sharedPost;
            $sharedPost = [
                'id' => $sp->id,
                'content' => $sp->content,
                'type' => $sp->type,
                'media' => $sp->media->map(fn($m) => ['id'=>$m->id,'type'=>$m->type,'url'=>$m->url,'thumbnail'=>$m->thumbnail])->toArray(),
                'user' => $this->transformUser($sp->user, $userId),
                'created_at' => $sp->created_at,
            ];
        }

        return [
            'id' => $post->id,
            'type' => $post->type,
            'content' => $post->content,
            'location' => $post->location,
            'feeling' => $post->feeling,
            'privacy' => $post->privacy,
            'is_pinned' => $post->is_pinned,
            'comments_disabled' => $post->comments_disabled,
            'views_count' => $post->views_count,
            'likes_count' => $post->likes_count,
            'comments_count' => $post->comments_count,
            'shares_count' => $post->shares_count,
            'saves_count' => $post->saves_count,
            'poll_options' => $post->poll_options,
            'created_at' => $post->created_at,
            'media' => $post->media->map(fn($m) => ['id'=>$m->id,'type'=>$m->type,'url'=>cdn_url($m->url),'thumbnail'=>cdn_url($m->thumbnail),'duration'=>$m->duration])->toArray(),
            'user' => $displayUser,
            'user_reaction' => $post->userReaction?->type,
            'is_saved' => \App\Models\CommunitySavedPost::where('user_id',$userId)->where('post_id',$post->id)->exists(),
            'shared_post' => $sharedPost,
            'page_id' => $post->page_id,
            'page' => $post->page_id ? ['id'=>$post->page?->id,'name'=>$post->page?->name,'avatar'=>cdn_url($post->page?->avatar)] : null,
        ];
    }

    public function transformUser($user, int $userId): array
    {
        if (!$user) return ['id' => 0, 'name' => 'Unknown', 'username' => null, 'avatar' => null, 'bio' => null, 'is_verified' => false, 'is_business' => false, 'followers_count' => 0, 'following_count' => 0, 'posts_count' => 0, 'is_following' => false, 'is_me' => false, 'cover_photo' => null, 'location' => null, 'website' => null];
        $profile = $user->communityProfile;
        return [
            'id' => $user->id,
            'name' => $user->name ?? 'User',
            'username' => $profile?->username,
            'avatar' => $user->avatar ?? null,
            'cover_photo' => $profile ? cdn_url($profile->getRawOriginal('cover_photo')) : null,
            'bio' => $profile?->bio,
            'location' => null,
            'website' => $profile?->website,
            'is_verified' => $profile?->is_verified ?? false,
            'is_business' => $profile?->is_business ?? false,
            'followers_count' => $profile?->followers_count ?? 0,
            'following_count' => $profile?->following_count ?? 0,
            'posts_count' => $profile?->posts_count ?? 0,
            'is_following' => CommunityFollow::where('follower_id',$userId)->where('following_id',$user->id)->exists(),
            'is_me' => $user->id === $userId,
        ];
    }
}