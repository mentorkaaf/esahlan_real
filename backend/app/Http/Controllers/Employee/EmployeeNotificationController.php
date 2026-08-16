<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\HR\EmployeeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeeNotificationController extends Controller
{
    private function me()
    {
        return Auth::guard('employee')->user();
    }

    /** JSON: unread count (for badge polling) */
    public function unreadCount()
    {
        $count = EmployeeNotification::where('employee_id', $this->me()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    /** JSON: latest 20 notifications */
    public function index()
    {
        $notifs = EmployeeNotification::where('employee_id', $this->me()->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'title'      => $n->title,
                'body'       => $n->body,
                'icon'       => $n->icon,
                'color'      => EmployeeNotification::colorFor($n->type),
                'url'        => $n->url,
                'read'       => $n->isRead(),
                'time'       => $n->created_at->diffForHumans(),
            ]);

        return response()->json([
            'notifications' => $notifs,
            'unread'        => $notifs->where('read', false)->count(),
        ]);
    }

    /** Mark single notification read */
    public function markRead(EmployeeNotification $notification)
    {
        $employee = $this->me();
        if ($notification->employee_id !== $employee->id) {
            abort(403);
        }
        $notification->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    /** Mark all read */
    public function markAllRead()
    {
        EmployeeNotification::where('employee_id', $this->me()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
