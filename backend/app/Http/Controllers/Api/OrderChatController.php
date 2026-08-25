<?php

namespace App\Http\Controllers\Api;

use App\Events\CustomerLocationUpdated;
use App\Events\OrderChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderChatController extends Controller
{
    // ── GET /orders/{order}/chat ─────────────────────────────────────────────
    public function messages(Request $request, Order $order)
    {
        if (!$this->canAccess($request, $order)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $msgs = DB::table('order_chat_messages')
            ->where('order_id', $order->id)
            ->orderBy('created_at', 'asc')
            ->get(['id', 'message_type', 'sender_type', 'sender_id', 'message',
                   'voice_url', 'lat', 'lng', 'is_read', 'created_at']);

        // Mark unread messages from the other party as read
        $myType = $this->senderType($request);
        DB::table('order_chat_messages')
            ->where('order_id', $order->id)
            ->where('sender_type', '!=', $myType)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true, 'data' => $msgs]);
    }

    // ── POST /orders/{order}/chat ────────────────────────────────────────────
    // Handles: text message, voice upload, location_request
    public function send(Request $request, Order $order)
    {
        if (!$this->canAccess($request, $order)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $msgType = $request->input('message_type', 'text');

        // Validate by message type
        match ($msgType) {
            'voice'            => $request->validate(['voice' => 'required|file|mimes:m4a,aac,mp3,webm,ogg|max:10240']),
            'location_request' => null,  // no extra validation needed
            default            => $request->validate(['message' => 'required|string|max:1000']),
        };

        $senderType = $this->senderType($request);
        $senderId   = $this->senderId($request);
        $senderName = $this->senderName($request);

        // Build row
        $row = [
            'order_id'     => $order->id,
            'message_type' => $msgType,
            'sender_type'  => $senderType,
            'sender_id'    => $senderId,
            'message'      => null,
            'voice_url'    => null,
            'lat'          => null,
            'lng'          => null,
            'is_read'      => false,
            'created_at'   => now(),
            'updated_at'   => now(),
        ];

        if ($msgType === 'voice') {
            $file = $request->file('voice');
            $dir  = "voice-messages/{$order->id}";
            $name = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs($dir, $name, 'public');
            $row['voice_url'] = Storage::disk('public')->url($path);
            $row['message']   = '🎵 Voice message';
        } elseif ($msgType === 'location_request') {
            $row['message'] = '📍 Location requested';
        } else {
            $row['message'] = $request->message;
        }

        $id = DB::table('order_chat_messages')->insertGetId($row);

        $createdAt = now()->toIso8601String();

        // Broadcast via Reverb
        broadcast(new OrderChatMessageSent(
            orderId:     $order->id,
            messageId:   $id,
            senderType:  $senderType,
            senderName:  $senderName,
            messageType: $msgType,
            message:     $row['message'],
            createdAt:   $createdAt,
            voiceUrl:    $row['voice_url'],
        ));

        // FCM to the other party (only for text + voice, not location_request)
        if (in_array($msgType, ['text', 'voice'])) {
            $fcmBody = $msgType === 'voice' ? '🎵 Voice message' : $row['message'];
            $this->notifyOtherParty($order, $senderType, $senderName, $fcmBody);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id'           => $id,
                'message_type' => $msgType,
                'sender_type'  => $senderType,
                'sender_name'  => $senderName,
                'message'      => $row['message'],
                'voice_url'    => $row['voice_url'],
                'created_at'   => $createdAt,
            ],
        ]);
    }

    // ── POST /orders/{order}/chat/location (customer only) ───────────────────
    public function shareLocation(Request $request, Order $order)
    {
        if (!$this->canAccess($request, $order)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        if ((int) $order->user_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Only the customer can share location'], 403);
        }

        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $lat = (float) $request->lat;
        $lng = (float) $request->lng;

        // Save as a location-type chat message
        $senderType = $this->senderType($request);
        $id = DB::table('order_chat_messages')->insertGetId([
            'order_id'     => $order->id,
            'message_type' => 'location',
            'sender_type'  => $senderType,
            'sender_id'    => $request->user()->id,
            'message'      => '📍 Location shared',
            'lat'          => $lat,
            'lng'          => $lng,
            'is_read'      => false,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $createdAt = now()->toIso8601String();

        // Broadcast as a new_message event (so chat updates in real time)
        broadcast(new OrderChatMessageSent(
            orderId:     $order->id,
            messageId:   $id,
            senderType:  $senderType,
            senderName:  $this->senderName($request),
            messageType: 'location',
            message:     '📍 Location shared',
            createdAt:   $createdAt,
            lat:         $lat,
            lng:         $lng,
        ));

        // Also broadcast driver_location event for real-time map update
        broadcast(new CustomerLocationUpdated(
            orderId: $order->id,
            lat:     $lat,
            lng:     $lng,
        ));

        return response()->json(['success' => true, 'data' => ['id' => $id, 'lat' => $lat, 'lng' => $lng]]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function canAccess(Request $request, Order $order): bool
    {
        $user = $request->user();
        if (!$user) return false;

        if ((int) $order->user_id === (int) $user->id) return true;

        if ($order->deliveryman_id) {
            $dm = \App\Models\Deliveryman::where('user_id', $user->id)->first();
            if ($dm && (int) $dm->id === (int) $order->deliveryman_id) return true;
        }

        return false;
    }

    private function senderType(Request $request): string
    {
        $user = $request->user();
        $dm = \App\Models\Deliveryman::where('user_id', $user->id)->exists();
        return $dm ? 'driver' : 'customer';
    }

    private function senderId(Request $request): int
    {
        return $request->user()->id;
    }

    private function senderName(Request $request): string
    {
        return $request->user()->name ?? 'User';
    }

    private function notifyOtherParty(Order $order, string $senderType, string $senderName, string $message): void
    {
        try {
            if ($senderType === 'customer') {
                $order->load('deliveryman.user');
                $token = $order->deliveryman?->fcm_token ?? $order->deliveryman?->user?->fcm_token;
                if ($token) {
                    FcmService::sendToToken($token,
                        "💬 {$senderName}",
                        $message,
                        ['type' => 'order_chat', 'order_id' => (string) $order->id, 'deep_link' => '/orders'],
                        null,
                        'esahlan_driver_v1'
                    );
                }
            } else {
                $order->load('user');
                $token = $order->user?->fcm_token;
                if ($token) {
                    FcmService::sendToToken($token,
                        "🚴 Driver: {$senderName}",
                        $message,
                        ['type' => 'order_chat', 'order_id' => (string) $order->id, 'deep_link' => '/orders/' . $order->id]
                    );
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('[OrderChat] FCM notify failed', [
                'order_id'    => $order->id,
                'sender_type' => $senderType,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
