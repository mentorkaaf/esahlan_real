<?php
namespace App\Http\Controllers\Api\Community;
use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\CommunityPostMedia;
use App\Models\CommunityPostReaction;
use App\Models\CommunityHashtag;
use App\Models\CommunitySavedPost;
use App\Models\CommunityNotification;
use App\Models\CommunityPollVote;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CommunityPostController extends Controller
{
    private CommunityFeedController $feed;
    public function __construct() { $this->feed = new CommunityFeedController(); }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:text,image,video,reel,poll,service',
            'content' => 'nullable|string|max:5000',
            'privacy' => 'in:public,followers,private',
            'location' => 'nullable|string|max:255',
            'feeling' => 'nullable|string|max:100',
            'group_id' => 'nullable|exists:community_groups,id',
            'page_id' => 'nullable|exists:community_business_pages,id',
            'poll_options' => 'nullable|array|min:2|max:6',
            'poll_options.*' => 'string|max:100',
            'media.*' => 'nullable|file|mimes:jpg,jpeg,png,gif,mp4,mov|max:51200',
        ]);

        $post = CommunityPost::create([
            'user_id' => auth()->id(),
            'type' => $request->type,
            'content' => $request->content,
            'privacy' => $request->privacy ?? 'public',
            'location' => $request->location,
            'feeling' => $request->feeling,
            'group_id' => $request->group_id,
            'page_id' => $request->page_id,
            'poll_options' => $request->type === 'poll' ? array_map(fn($o) => ['text'=>$o,'votes'=>0], $request->poll_options ?? []) : null,
            'published_at' => now(),
        ]);

        // Handle media uploads
        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $i => $file) {
                $mime = $file->getMimeType();
                $isVideo = str_starts_with($mime, 'video/');
                $path = $file->store('community/posts', 'public');
                CommunityPostMedia::create([
                    'post_id' => $post->id,
                    'type' => $isVideo ? 'video' : 'image',
                    'url' => cdn_url($path),
                    'sort_order' => $i,
                ]);
            }
        }

        // Extract & save hashtags
        $this->extractHashtags($post);

        // Update profile post count
        auth()->user()->communityProfile?->increment('posts_count');

        $post->load(['user.communityProfile','media','userReaction']);
        return response()->json(['status'=>'success','data'=>$this->feed->transformPost($post, auth()->id())], 201);
    }

    public function show(int $id)
    {
        $post = CommunityPost::with(['user.communityProfile','media','userReaction'])->findOrFail($id);
        $post->increment('views_count');
        return response()->json(['status'=>'success','data'=>$this->feed->transformPost($post, auth()->id())]);
    }

    public function update(Request $request, int $id)
    {
        $post = CommunityPost::where('user_id', auth()->id())->findOrFail($id);
        $post->update($request->only(['content','privacy','location','feeling']));
        $this->extractHashtags($post);
        $post->load(['user.communityProfile','media','userReaction']);
        return response()->json(['status'=>'success','data'=>$this->feed->transformPost($post, auth()->id())]);
    }

    public function destroy(int $id)
    {
        $post = CommunityPost::where('user_id', auth()->id())->findOrFail($id);
        auth()->user()->communityProfile?->decrement('posts_count');
        $post->delete();
        return response()->json(['status'=>'success','message'=>'Post deleted']);
    }

    public function react(Request $request, int $id)
    {
        $request->validate(['type' => 'required|in:like,love,wow,haha,sad,angry']);
        $post = CommunityPost::findOrFail($id);
        $userId = auth()->id();

        $existing = CommunityPostReaction::where('post_id',$id)->where('user_id',$userId)->first();

        if ($existing) {
            if ($existing->type === $request->type) {
                $existing->delete();
                $post->decrement('likes_count');
                return response()->json(['status'=>'success','reacted'=>false,'likes_count'=>$post->fresh()->likes_count]);
            }
            $existing->update(['type'=>$request->type]);
        } else {
            CommunityPostReaction::create(['post_id'=>$id,'user_id'=>$userId,'type'=>$request->type]);
            $post->increment('likes_count');
            // Notify post owner
            if ($post->user_id !== $userId) {
                CommunityNotification::create(['user_id'=>$post->user_id,'actor_id'=>$userId,'type'=>'like','notifiable_type'=>'post','notifiable_id'=>$id]);
                $owner = \App\Models\User::find($post->user_id);
                if ($owner?->fcm_token) {
                    $actor = auth()->user();
                    FcmService::sendToToken($owner->fcm_token, 'New Like', "{$actor->name} liked your post", ['type'=>'post_like','post_id'=>(string)$id]);
                }
            }
        }

        return response()->json(['status'=>'success','reacted'=>true,'reaction'=>$request->type,'likes_count'=>$post->fresh()->likes_count]);
    }

    public function share(Request $request, int $id)
    {
        $original = CommunityPost::findOrFail($id);
        $share = CommunityPost::create([
            'user_id' => auth()->id(),
            'type' => 'share',
            'shared_post_id' => $id,
            'content' => $request->content,
            'privacy' => $request->privacy ?? 'public',
            'published_at' => now(),
        ]);
        $original->increment('shares_count');
        // Notify original post owner
        $userId = auth()->id();
        if ($original->user_id !== $userId) {
            CommunityNotification::create(['user_id'=>$original->user_id,'actor_id'=>$userId,'type'=>'share','notifiable_type'=>'post','notifiable_id'=>$id]);
            $owner = \App\Models\User::find($original->user_id);
            if ($owner?->fcm_token) {
                $actor = auth()->user();
                FcmService::sendToToken($owner->fcm_token, 'Post Shared', "{$actor->name} shared your post", ['type'=>'post_share','post_id'=>(string)$id]);
            }
        }
        $share->load(['user.communityProfile','media','userReaction']);
        return response()->json(['status'=>'success','data'=>$this->feed->transformPost($share, auth()->id())], 201);
    }

    public function save(int $id)
    {
        $post = CommunityPost::findOrFail($id);
        $existing = CommunitySavedPost::where('user_id',auth()->id())->where('post_id',$id)->first();
        if ($existing) {
            $existing->delete();
            $post->decrement('saves_count');
            return response()->json(['status'=>'success','saved'=>false]);
        }
        CommunitySavedPost::create(['user_id'=>auth()->id(),'post_id'=>$id]);
        $post->increment('saves_count');
        return response()->json(['status'=>'success','saved'=>true]);
    }

    public function saved()
    {
        $userId = auth()->id();
        $posts = CommunityPost::with(['user.communityProfile','media','userReaction'])
            ->whereHas('saves', fn($q) => $q->where('user_id',$userId))
            ->latest()->paginate(15);
        $feed = new CommunityFeedController();
        return response()->json(['status'=>'success','data'=>$posts->map(fn($p)=>$feed->transformPost($p,$userId))->toArray()]);
    }

    public function votePoll(Request $request, int $id)
    {
        $request->validate(['option_index'=>'required|integer|min:0']);
        $post = CommunityPost::where('type','poll')->findOrFail($id);
        if (\App\Models\CommunityPollVote::where('post_id',$id)->where('user_id',auth()->id())->exists()) {
            return response()->json(['status'=>'error','message'=>'Already voted'], 422);
        }
        \App\Models\CommunityPollVote::create(['post_id'=>$id,'user_id'=>auth()->id(),'option_index'=>$request->option_index]);
        $options = $post->poll_options;
        $options[$request->option_index]['votes'] = ($options[$request->option_index]['votes'] ?? 0) + 1;
        $post->update(['poll_options'=>$options]);
        return response()->json(['status'=>'success','poll_options'=>$options]);
    }

    private function extractHashtags(CommunityPost $post): void
    {
        if (!$post->content) return;
        preg_match_all('/#(\w+)/', $post->content, $matches);
        $tags = array_unique($matches[1]);
        $ids = [];
        foreach ($tags as $tag) {
            $ht = CommunityHashtag::firstOrCreate(['name'=>strtolower($tag)]);
            $ht->increment('posts_count');
            $ids[] = $ht->id;
        }
        $post->hashtags()->sync($ids);
    }
}