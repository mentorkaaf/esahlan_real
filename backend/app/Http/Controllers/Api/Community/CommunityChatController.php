<?php
namespace App\Http\Controllers\Api\Community;
use App\Http\Controllers\Controller;
use App\Models\CommunityChat;
use App\Models\CommunityChatMember;
use App\Models\CommunityMessage;
use App\Models\User;
use App\Services\AutoRestrictService;
use App\Services\FcmService;
use App\Services\PrivacyService;
use App\Services\RealtimeService;
use Illuminate\Http\Request;

class CommunityChatController extends Controller
{
    public function index()
    {
        $userId = auth()->id();
        $chatIds = CommunityChatMember::where('user_id',$userId)->pluck('chat_id');

        $chats = CommunityChat::with(['members.user.communityProfile','lastMessage.user'])
            ->whereIn('id',$chatIds)
            ->latest()
            ->paginate(20);

        return response()->json(['status'=>'success','data'=>$chats->map(fn($c) => $this->transformChat($c, $userId))->toArray()]);
    }

    public function startOrGet(int $userId)
    {
        $me = auth()->id();
        if ($me === $userId) return response()->json(['status'=>'error'],422);

        // Check privacy: who_can_message
        $check = PrivacyService::canMessage($me, $userId);
        if (!$check['allowed']) {
            return response()->json(['status'=>'error','message'=>$check['reason']],403);
        }

        // Find existing direct chat
        $existing = CommunityChat::where('type','direct')
            ->whereHas('members', fn($q) => $q->where('user_id',$me))
            ->whereHas('members', fn($q) => $q->where('user_id',$userId))
            ->first();

        if ($existing) return response()->json(['status'=>'success','data'=>$this->transformChat($existing, $me)]);

        $chat = CommunityChat::create(['type'=>'direct']);
        CommunityChatMember::create(['chat_id'=>$chat->id,'user_id'=>$me,'role'=>'member']);
        CommunityChatMember::create(['chat_id'=>$chat->id,'user_id'=>$userId,'role'=>'member']);
        $chat->load('members.user.communityProfile');

        return response()->json(['status'=>'success','data'=>$this->transformChat($chat, $me)], 201);
    }

    public function messages(int $chatId)
    {
        $userId = auth()->id();
        CommunityChatMember::where('chat_id',$chatId)->where('user_id',$userId)->firstOrFail();
        $messages = CommunityMessage::with(['user.communityProfile','replyTo.user'])
            ->where('chat_id',$chatId)->latest()->paginate(30);

        // Mark as read
        CommunityChatMember::where('chat_id',$chatId)->where('user_id',$userId)->update(['last_read_at'=>now()]);

        return response()->json(['status'=>'success','data'=>$messages->items(),'meta'=>['current_page'=>$messages->currentPage(),'last_page'=>$messages->lastPage()]]);
    }

    public function send(Request $request, int $chatId)
    {
        AutoRestrictService::enforce(auth()->id(), 'message');

        $request->validate(['type'=>'in:text,image,video,audio,voice','content'=>'nullable|string|max:2000','media'=>'nullable|file|max:51200','reply_to_id'=>'nullable|exists:community_messages,id']);
        $userId = auth()->id();
        CommunityChatMember::where('chat_id',$chatId)->where('user_id',$userId)->firstOrFail();

        $mediaUrl = null;
        if ($request->hasFile('media')) {
            $path = $request->file('media')->store('community/messages','public');
            $mediaUrl = cdn_url($path);
        }

        $msg = CommunityMessage::create([
            'chat_id' => $chatId,
            'user_id' => $userId,
            'type' => $request->type ?? 'text',
            'content' => $request->content,
            'media_url' => $mediaUrl,
            'reply_to_id' => $request->reply_to_id,
        ]);

        $msg->load(['user.communityProfile','replyTo.user']);

        // Notify other chat members
        $sender = auth()->user();
        $otherMembers = CommunityChatMember::where('chat_id', $chatId)->where('user_id', '!=', $userId)->with('user')->get();
        foreach ($otherMembers as $member) {
            if ($member->user?->fcm_token) {
                $preview = $msg->type === 'text' ? ($msg->content ?? '') : '📎 Media';
                FcmService::sendToToken($member->user->fcm_token, $sender->name, $preview, ['type'=>'chat_message','chat_id'=>(string)$chatId,'message_id'=>(string)$msg->id]);
            }
        }

        // Realtime delivery to chat members who currently have the app open
        $msgPayload = [
            'chat_id' => $chatId,
            'message' => [
                'id' => $msg->id,
                'type' => $msg->type,
                'content' => $msg->content,
                'media_url' => $msg->media_url,
                'reply_to_id' => $msg->reply_to_id,
                'user' => ['id' => $msg->user->id, 'name' => $msg->user->name, 'avatar' => $msg->user->avatar],
                'created_at' => $msg->created_at,
            ],
        ];
        RealtimeService::toChat($chatId, 'chat.message_sent', $msgPayload);

        // Also notify each member's private channel so inbox updates without re-opening app
        foreach ($otherMembers as $member) {
            RealtimeService::toUser($member->user_id, 'chat.inbox_update', [
                'chat_id'  => $chatId,
                'preview'  => $msg->type === 'text' ? ($msg->content ?? '') : '📎 Media',
                'sender'   => $msg->user->name,
            ]);
        }

        return response()->json(['status'=>'success','data'=>$msg], 201);
    }

    public function markRead(int $chatId)
    {
        $userId = auth()->id();
        CommunityChatMember::where('chat_id', $chatId)->where('user_id', $userId)->update(['last_read_at' => now()]);
        CommunityMessage::where('chat_id', $chatId)->where('user_id', '!=', $userId)->whereNull('read_at')->update(['read_at' => now()]);

        RealtimeService::toChat($chatId, 'chat.seen', [
            'chat_id' => $chatId,
            'seen_by' => $userId,
            'seen_at' => now()->toIso8601String(),
        ]);

        return response()->json(['status' => 'success']);
    }

    /** Ephemeral typing indicator — not persisted, just relayed over the chat presence channel. */
    public function typing(Request $request, int $chatId)
    {
        $userId = auth()->id();
        CommunityChatMember::where('chat_id', $chatId)->where('user_id', $userId)->firstOrFail();

        RealtimeService::toChatPresence($chatId, 'chat.typing', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'is_typing' => (bool) $request->boolean('is_typing', true),
        ]);

        return response()->json(['status' => 'success']);
    }

    public function reactToMessage(Request $request, int $msgId)
    {
        $request->validate(['emoji' => 'required|string|max:10']);
        $msg = CommunityMessage::findOrFail($msgId);
        $userId = auth()->id();
        $existing = \DB::table('community_message_reactions')->where('message_id', $msgId)->where('user_id', $userId)->first();
        if ($existing) {
            if ($existing->emoji === $request->emoji) {
                \DB::table('community_message_reactions')->where('id', $existing->id)->delete();
                return response()->json(['status' => 'success', 'reacted' => false]);
            }
            \DB::table('community_message_reactions')->where('id', $existing->id)->update(['emoji' => $request->emoji, 'updated_at' => now()]);
        } else {
            \DB::table('community_message_reactions')->insert(['message_id' => $msgId, 'user_id' => $userId, 'emoji' => $request->emoji, 'created_at' => now(), 'updated_at' => now()]);
        }
        return response()->json(['status' => 'success', 'reacted' => true, 'emoji' => $request->emoji]);
    }

    public function deleteMessage(int $msgId)
    {
        $msg = CommunityMessage::where('user_id',auth()->id())->findOrFail($msgId);
        $msg->update(['is_deleted'=>true,'content'=>'Message deleted']);
        return response()->json(['status'=>'success']);
    }

    private function transformChat($chat, int $userId): array
    {
        $other = $chat->type === 'direct'
            ? $chat->members->firstWhere('user_id','!=',$userId)?->user
            : null;
        $feed = new CommunityFeedController();

        return [
            'id' => $chat->id,
            'type' => $chat->type,
            'name' => $chat->type === 'group' ? $chat->name : $other?->name,
            'avatar' => $chat->type === 'group' ? $chat->avatar : $other?->avatar,
            'other_user' => $other ? $feed->transformUser($other, $userId) : null,
            'last_message' => $chat->lastMessage?->first() ? [
                'id' => $chat->lastMessage->first()->id,
                'type' => $chat->lastMessage->first()->type,
                'content' => $chat->lastMessage->first()->is_deleted ? 'Message deleted' : $chat->lastMessage->first()->content,
                'created_at' => $chat->lastMessage->first()->created_at,
                'is_mine' => $chat->lastMessage->first()->user_id === $userId,
            ] : null,
            'unread_count' => $chat->unreadCount($userId),
            'members_count' => $chat->members->count(),
        ];
    }
}