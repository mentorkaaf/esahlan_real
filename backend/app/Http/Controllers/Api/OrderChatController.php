<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            ->get(['id', 'sender_type', 'sender_id', 'message', 'is_read', 'created_at']);

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
    public function send(Request $request, Order $order)
    {
        if (!$this->canAccess($request, $order)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $request->validate(['message' => 'required|string|max:1000']);

        $senderType = $this->senderType($request);
        $senderId   = $this->senderId($request);
        $senderName = $this->senderName($request);

        $id = DB::table('order_chat_messages')->insertGetId([
            'order_id'    => $order->id,
            'sender_type' => $senderType,
            'sender_id'   => $senderId,
            'message'     => $request->message,
            'is_read'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $createdAt = now()->toIso8601String();

        // Broadcast via Reverb
        broadcast(new OrderChatMessageSent(
            orderId:    $order->id,
            messageId:  $id,
            senderType: $senderType,
            senderName: $senderName,
            message:    $request->message,
            createdAt:  $createdAt,
        ));

        // FCM to the other party
        $this->notifyOtherParty($order, $senderType, $senderName, $request->message);

        return response()->json([
            'success' => true,
            'data' => [
                'id'          => $id,
                'sender_type' => $senderType,
                'sender_name' => $senderName,
                'message'     => $request->message,
                'created_at'  => $createdAt,
            ],
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function canAccess(Request $request, Order $order): bool
    {
        $user = $request->user();
        if (!$user) return false;

        // Customer owns the order
        if ($order->user_id === $user->id) return true;

        // Driver is assigned to the order
        if ($order->deliveryman_id) {
            $dm = \App\Models\Deliveryman::where('user_id', $user->id)->first();
            if ($dm && $dm->id === $order->deliveryman_id) return true;
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
                // Notify driver
                $order->load('deliveryman.user');
                $token = $order->deliveryman?->fcm_token ?? $order->deliveryman?->user?->fcm_token;
                if ($token) {
                    FcmService::sendToToken($token,
                        "💬 {$senderName}",
                        $message,
                        [
                            'type'     => 'order_chat',
                            'order_id' => (string) $order->id,
                            'deep_link'=> '/orders',
                        ],
                        null,
                        'esahlan_driver_v1'
                    );
                }
            } else {
                // Notify customer
                $order->load('user');
                $token = $order->user?->fcm_token;
                if ($token) {
                    FcmService::sendToToken($token,
                        "🚴 Driver: {$senderName}",
                        $message,
                        [
                            'type'      => 'order_chat',
                            'order_id'  => (string) $order->id,
                            'deep_link' => '/orders/' . $order->id,
                        ]
                    );
                }
            }
        } catch (\Throwable) {}
    }
}
