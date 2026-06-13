<?php
namespace App\Http\Controllers\Api\Community;
use App\Http\Controllers\Controller;
use App\Models\CommunityStory;
use App\Models\CommunityStoryView;
use App\Services\FcmService;
use Illuminate\Http\Request;

class CommunityStoryController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:image,video,text',
            'text_content' => 'nullable|string|max:500',
            'bg_color' => 'nullable|string|max:20',
            'location' => 'nullable|string|max:255',
            'media' => 'nullable|file|mimes:jpg,jpeg,png,mp4,mov|max:51200',
        ]);

        $mediaUrl = null;
        if ($request->hasFile('media')) {
            $path = $request->file('media')->store('community/stories','public');
            $mediaUrl = asset('storage/'.$path);
        }

        $story = CommunityStory::create([
            'user_id' => auth()->id(),
            'type' => $request->type,
            'media_url' => $mediaUrl,
            'text_content' => $request->text_content,
            'bg_color' => $request->bg_color ?? '#140465',
            'location' => $request->location,
            'expires_at' => now()->addHours(24),
        ]);

        return response()->json(['status'=>'success','data'=>$story], 201);
    }

    public function view(int $id)
    {
        $story = CommunityStory::findOrFail($id);
        if (!CommunityStoryView::where('story_id',$id)->where('user_id',auth()->id())->exists()) {
            CommunityStoryView::create(['story_id'=>$id,'user_id'=>auth()->id()]);
            $story->increment('views_count');
        }
        return response()->json(['status'=>'success']);
    }

    public function destroy(int $id)
    {
        CommunityStory::where('user_id',auth()->id())->findOrFail($id)->delete();
        return response()->json(['status'=>'success','message'=>'Story deleted']);
    }

    public function viewers(int $id)
    {
        $story = CommunityStory::where('user_id',auth()->id())->findOrFail($id);
        $viewers = CommunityStoryView::with('user.communityProfile')->where('story_id',$id)->latest()->get();
        $feed = new CommunityFeedController();
        $authId = auth()->id();
        return response()->json(['status'=>'success','data'=>$viewers->map(fn($v)=>$feed->transformUser($v->user,$authId))->toArray()]);
    }

    public function react(\Illuminate\Http\Request $request, int $id)
    {
        $request->validate(['emoji' => 'required|string|max:10']);
        $story = CommunityStory::findOrFail($id);
        $userId = auth()->id();

        // Upsert reaction (one per user per story)
        \Illuminate\Support\Facades\DB::table('community_story_reactions')
            ->updateOrInsert(
                ['story_id' => $id, 'user_id' => $userId],
                ['emoji' => $request->emoji, 'created_at' => now(), 'updated_at' => now()]
            );

        // Notify story owner
        if ($story->user_id !== $userId) {
            $owner = \App\Models\User::find($story->user_id);
            if ($owner?->fcm_token) {
                $actor = auth()->user();
                FcmService::sendToToken($owner->fcm_token, 'Story Reaction', "{$actor->name} reacted {$request->emoji} to your story", ['type'=>'story_reaction','story_id'=>(string)$id]);
            }
        }

        return response()->json(['status'=>'success']);
    }

    public function comment(\Illuminate\Http\Request $request, int $id)
    {
        $request->validate(['content' => 'required|string|max:500']);
        $story = CommunityStory::findOrFail($id);
        $userId = auth()->id();

        $comment = \Illuminate\Support\Facades\DB::table('community_story_comments')->insertGetId([
            'story_id' => $id,
            'user_id' => $userId,
            'content' => $request->content,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Notify story owner
        if ($story->user_id !== $userId) {
            $owner = \App\Models\User::find($story->user_id);
            if ($owner?->fcm_token) {
                $actor = auth()->user();
                FcmService::sendToToken($owner->fcm_token, 'Story Comment', "{$actor->name} commented on your story", ['type'=>'story_comment','story_id'=>(string)$id]);
            }
        }

        $user = auth()->user()->load('communityProfile');
        return response()->json(['status'=>'success','data'=>[
            'id' => $comment,
            'content' => $request->content,
            'user' => ['id'=>$user->id,'name'=>$user->name,'avatar'=>$user->avatar],
            'created_at' => now(),
        ]], 201);
    }
}