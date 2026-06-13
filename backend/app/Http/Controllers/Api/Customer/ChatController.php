<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function conversations(Request $request)
    {
        $conversations = Conversation::whereHas('order', fn($q) =>
            $q->where('user_id', $request->user()->id)
        )
        ->with(['order.vendor'])
        ->latest()
        ->get();

        return response()->json(['success' => true, 'data' => $conversations]);
    }

    public function messages(Request $request, Conversation $conversation)
    {
        $order = $conversation->order;
        if (!$order || $order->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $messages = Message::where('conversation_id', $conversation->id)->oldest()->get();

        // Mark as read
        Message::where('conversation_id', $conversation->id)
            ->where('sender_type', '!=', get_class($request->user()))
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true, 'data' => $messages]);
    }

    public function send(Request $request, Conversation $conversation)
    {
        $order = $conversation->order;
        if (!$order || $order->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $request->validate([
            'type' => 'required|in:text,image',
            'body' => 'required_if:type,text|string|max:1000',
            'file' => 'required_if:type,image|image|max:2048',
        ]);

        $data = [
            'conversation_id' => $conversation->id,
            'sender_type'     => get_class($request->user()),
            'sender_id'       => $request->user()->id,
            'type'            => $request->type,
            'body'            => $request->body,
        ];

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('chat', 'public');
        }

        $message = Message::create($data);
        return response()->json(['success' => true, 'data' => $message, 'message' => 'Message sent'], 201);
    }

    public function getOrCreateByOrder(Request $request)
    {
        $request->validate(['order_id' => 'required|exists:orders,id']);
        $order = Order::where('id', $request->order_id)->where('user_id', $request->user()->id)->firstOrFail();

        $conversation = Conversation::firstOrCreate(
            ['order_id' => $order->id],
            ['type' => 'order']
        );

        return response()->json(['success' => true, 'data' => $conversation]);
    }
}
