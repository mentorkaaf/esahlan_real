<?php
namespace App\Http\Controllers\Api\Community;
use App\Http\Controllers\Controller;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityNotification;
use App\Models\User;
use App\Services\FcmService;
use App\Services\InteractionTracker;
use App\Services\RealtimeService;
use Illuminate\Http\Request;

class CommunityCommentController extends Controller
{
    public function index(int $postId)
    {
        $comments = CommunityComment::with(['user.communityProfile','replies.user.communityProfile'])
            ->where('post_id',$postId)
            ->whereNull('parent_id')
            ->latest()
            ->paginate(20);

        return response()->json(['status'=>'success','data'=>$comments]);
    }

    public function store(Request $request, int $postId)
    {
        $request->validate([
            'content' => 'nullable|string|max:2000',
            'parent_id' => 'nullable|exists:community_comments,id',
            'media' => 'nullable|file|mimes:jpg,jpeg,png,gif,mp3,m4a,ogg,wav,aac|max:20480',
            'type' => 'nullable|in:text,image,voice',
        ]);
        $post = CommunityPost::findOrFail($postId);

        $mediaUrl = null;
        $commentType = $request->type ?? 'text';
        if ($request->hasFile('media')) {
            $path = $request->file('media')->store('community/comment-media', 'public');
            $mediaUrl = cdn_url($path);
        }

        $comment = CommunityComment::create([
            'post_id' => $postId,
            'user_id' => auth()->id(),
            'parent_id' => $request->parent_id,
            'content' => $request->content ?? ($commentType === 'voice' ? 'Voice message' : 'Image'),
            'media_url' => $mediaUrl,
            'media_type' => $commentType !== 'text' ? $commentType : null,
        ]);

        if ($request->parent_id) {
            CommunityComment::find($request->parent_id)?->increment('replies_count');
        }
        $post->increment('comments_count');

        // Notify post owner
        if ($post->user_id !== auth()->id()) {
            CommunityNotification::create(['user_id'=>$post->user_id,'actor_id'=>auth()->id(),'type'=>'comment','notifiable_type'=>'post','notifiable_id'=>$postId]);
            $owner = \App\Models\User::find($post->user_id);
            if ($owner?->fcm_token) {
                $actor = auth()->user();
                FcmService::sendToToken($owner->fcm_token, 'New Comment', "{$actor->name} commented on your post", ['type'=>'post_comment','post_id'=>(string)$postId]);
            }
        }

        InteractionTracker::track(auth()->id(), $postId, 'comment');
        $comment->load('user.communityProfile');

        // Lightweight realtime payload — not the full comment model, just
        // what a feed/post card needs to render the new comment live.
        RealtimeService::toPublic("community.post.{$postId}", 'post.comment_added', [
            'post_id' => $postId,
            'comments_count' => $post->fresh()->comments_count,
            'comment' => [
                'id' => $comment->id,
                'content' => $comment->content,
                'media_type' => $comment->media_type,
                'parent_id' => $comment->parent_id,
                'user' => [
                    'id' => $comment->user->id,
                    'name' => $comment->user->name,
                    'avatar' => $comment->user->avatar,
                ],
                'created_at' => $comment->created_at,
            ],
        ]);

        return response()->json(['status'=>'success','data'=>$comment], 201);
    }

    public function update(Request $request, int $id)
    {
        $comment = CommunityComment::where('user_id',auth()->id())->findOrFail($id);
        $comment->update(['content'=>$request->content]);
        return response()->json(['status'=>'success','data'=>$comment]);
    }

    public function destroy(int $id)
    {
        $comment = CommunityComment::where('user_id',auth()->id())->findOrFail($id);
        $postId = $comment->post_id;
        if ($post = CommunityPost::find($postId)) {
            $post->decrement('comments_count');
            RealtimeService::toPublic("community.post.{$postId}", 'post.comment_removed', [
                'post_id' => $postId,
                'comment_id' => $id,
                'comments_count' => $post->fresh()->comments_count,
            ]);
        }
        $comment->delete();
        return response()->json(['status'=>'success','message'=>'Comment deleted']);
    }

    public function react(Request $request, int $id)
    {
        $request->validate(['type'=>'required|in:like,love,wow,haha,sad']);
        $comment = CommunityComment::findOrFail($id);
        $existing = \App\Models\CommunityCommentReaction::where('comment_id',$id)->where('user_id',auth()->id())->first();
        if ($existing) {
            if ($existing->type === $request->type) {
                $existing->delete(); $comment->decrement('likes_count');
                return response()->json(['status'=>'success','reacted'=>false]);
            }
            $existing->update(['type'=>$request->type]);
        } else {
            \App\Models\CommunityCommentReaction::create(['comment_id'=>$id,'user_id'=>auth()->id(),'type'=>$request->type]);
            $comment->increment('likes_count');
        }
        return response()->json(['status'=>'success','reacted'=>true,'reaction'=>$request->type,'likes_count'=>$comment->fresh()->likes_count]);
    }
}