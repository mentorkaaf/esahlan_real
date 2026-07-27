<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    // GET /notifications?per_page=20
    public function index(Request $request)
    {
        $user = $request->user();

        $page = DB::table('notifications')
            ->where('notifiable_type', 'App\\Models\\User')
            ->where('notifiable_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate((int) ($request->query('per_page', 20)));

        $items = collect($page->items())->map(fn($n) => [
            'id'         => $n->id,
            'type'       => $n->type,
            'data'       => json_decode($n->data, true),
            'read'       => !is_null($n->read_at),
            'created_at' => $n->created_at,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'total'        => $page->total(),
                'unread_count' => DB::table('notifications')
                    ->where('notifiable_type', 'App\\Models\\User')
                    ->where('notifiable_id', $user->id)
                    ->whereNull('read_at')->count(),
            ],
        ]);
    }

    // GET /notifications/unread
    public function unreadCount(Request $request)
    {
        $count = DB::table('notifications')
            ->where('notifiable_type', 'App\\Models\\User')
            ->where('notifiable_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['success' => true, 'data' => ['count' => $count]]);
    }

    // POST /notifications/read  body: {id?: uuid, all?: true}
    public function markRead(Request $request)
    {
        $user = $request->user();
        $base = DB::table('notifications')
            ->where('notifiable_type', 'App\\Models\\User')
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at');

        if ($request->boolean('all')) {
            $base->update(['read_at' => now(), 'updated_at' => now()]);
        } elseif ($id = $request->input('id')) {
            $base->where('id', $id)->update(['read_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request)
    {
        DB::table('notifications')
            ->where('notifiable_type', 'App\\Models\\User')
            ->where('notifiable_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->json(['success' => true]);
    }
}
