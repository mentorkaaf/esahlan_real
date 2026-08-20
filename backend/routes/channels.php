<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\CommunityChatMember;
use App\Models\CommunityStory;
use App\Models\CommunityPost;

/*
|--------------------------------------------------------------------------
| eSahlan realtime channel naming convention
|--------------------------------------------------------------------------
| This file is the single source of truth for channel authorization across
| every module (Community, Chat, Delivery, Vendor, Wallet, Admin, future
| modules). New modules should follow these patterns rather than inventing
| new ones.
|
| IMPORTANT: route patterns below are written WITHOUT "private-"/"presence-"
| prefixes — Laravel strips those from the wire channel name before matching
| against this file, then re-adds the correct prefix based on which Channel
| class (PrivateChannel / PresenceChannel) the broadcasting code used. Two
| channels that only differ by that prefix collapse to the same route here,
| so private and presence variants of "the same" resource get distinct
| route names (e.g. "chat.{id}" vs "chat-presence.{id}").
|
|   user.{userId}            -> PrivateChannel  Personal channel — notifications,
|                                DM previews, wallet updates, order status,
|                                anything addressed to one specific user.
|                                Any module may broadcast here.
|
|   online                   -> PresenceChannel Global "who's online" presence.
|
|   chat.{chatId}            -> PrivateChannel  Chat messages, seen/delivered.
|   chat-presence.{chatId}   -> PresenceChannel Typing indicators + who's
|                                currently viewing this chat.
|
|   {module}.{resource}.{id} -> Channel (public) Non-sensitive aggregate data
|                                anyone viewing that resource needs (like/
|                                comment/share counts, viewer counts). No
|                                auth needed — these are just numbers, and
|                                public channels don't need an entry here at
|                                all (Reverb allows them by default).
|                                e.g. community.post.{id}, community.reel.{id}
|
|   {module}.{resource}.{id}.owner -> PrivateChannel  Owner-only sensitive
|                                data on a resource (e.g. who viewed your
|                                story, detailed analytics). Authorized
|                                against the resource's owning user_id.
*/

// Personal channel — used by every module for user-targeted realtime events
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Chat — only members of the chat can subscribe
Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    return CommunityChatMember::where('chat_id', $chatId)
        ->where('user_id', $user->id)
        ->exists();
});

// Chat presence — typing indicators + who's currently viewing, members only
Broadcast::channel('chat-presence.{chatId}', function ($user, $chatId) {
    $isMember = CommunityChatMember::where('chat_id', $chatId)
        ->where('user_id', $user->id)
        ->exists();
    if (!$isMember) return false;
    return ['id' => $user->id, 'name' => $user->name];
});

// Global online presence
Broadcast::channel('online', function ($user) {
    return ['id' => $user->id, 'name' => $user->name];
});

// Story owner — only the story's author can see live viewer/reaction details
Broadcast::channel('community.story.{storyId}.owner', function ($user, $storyId) {
    $story = CommunityStory::find($storyId);
    return $story && (int) $story->user_id === (int) $user->id;
});

// Post owner — only the post's author can see detailed (non-aggregate) analytics
Broadcast::channel('community.post.{postId}.owner', function ($user, $postId) {
    $post = CommunityPost::find($postId);
    return $post && (int) $post->user_id === (int) $user->id;
});

// Vendor channel — new orders and store events for the vendor app
Broadcast::channel('vendor.{vendorId}', function ($user, $vendorId) {
    $vendor = $user->vendor;
    if (!$vendor) return false;
    return (int) $vendor->id === (int) $vendorId
        && in_array($user->role?->slug ?? '', ['vendor_owner', 'vendor_employee']);
});

// ── eWholesale realtime channels ─────────────────────────────────────────────
// Buyer's personal wholesale channel (quotes, order updates, RFQ responses)
Broadcast::channel('ewholesale.buyer.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Supplier channel — new inquiries, orders, quote counters
Broadcast::channel('ewholesale.supplier.{vendorId}', function ($user, $vendorId) {
    $vendor = $user->vendor;
    if (!$vendor) return false;
    return (int) $vendor->id === (int) $vendorId;
});

// Order live tracking channel (buyer or supplier)
Broadcast::channel('ewholesale.order.{orderId}', function ($user, $orderId) {
    $order = \App\Models\EWholesale\EWOrder::find($orderId);
    if (!$order) return false;
    $buyer    = \App\Models\EWholesale\EWBuyer::where('user_id', $user->id)->first();
    $isbuyer  = $buyer && $buyer->id === $order->buyer_id;
    $isVendor = $user->vendor && $user->vendor->id === $order->supplier?->vendor_id;
    return $isbuyer || $isVendor;
});
// ─────────────────────────────────────────────────────────────────────────────

// Inbox support chat — user in conversation OR assigned admin/agent
Broadcast::channel('inbox.{conversationUuid}', function ($user, $conversationUuid) {
    $conv = \App\Models\InboxConversation::where('uuid', $conversationUuid)->first();
    if (!$conv) return false;
    // Allow: the conversation owner, the assigned agent, or any admin
    return (int) $conv->user_id  === (int) $user->id
        || (int) $conv->agent_id === (int) $user->id
        || in_array($user->role ?? '', ['super_admin', 'admin', 'support_agent']);
});
