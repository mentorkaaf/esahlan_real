<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = DB::table('push_notification_logs')
            ->where('user_id', $user->id)
            ->latest()
            ->paginate($request->per_page ?? 20);

        $unreadCount = DB::table('push_notification_logs')
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success'     => true,
            'data'        => $notifications,
            'unread_count'=> $unreadCount,
        ]);
    }

    public function markRead(Request $request, $id)
    {
        DB::table('push_notification_logs')
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->update(['read_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Marked as read']);
    }

    public function markAllRead(Request $request)
    {
        DB::table('push_notification_logs')
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true, 'message' => 'All notifications marked as read']);
    }
}
