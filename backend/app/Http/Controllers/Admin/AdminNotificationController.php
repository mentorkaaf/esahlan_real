<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PushNotificationLog;
use App\Services\Notification\NotificationService;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    public function index()
    {
        $logs        = PushNotificationLog::with('sentBy')->latest()->paginate(20);
        $totalSent   = PushNotificationLog::count();
        $sentToday   = PushNotificationLog::whereDate('created_at', today())->count();
        $activeDevices = User::whereNotNull('fcm_token')->count();
        return view('admin.notifications.index', compact('logs', 'totalSent', 'sentToday', 'activeDevices'));
    }

    public function send(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'body'        => 'required|string',
            'target_type' => 'required|in:all,customers,vendors,deliverymen,specific',
            'target_id'   => 'required_if:target_type,specific|nullable|exists:users,id',
        ]);

        // Build data payload (deep link + any extras)
        $data = array_filter([
            'screen'    => $request->deep_link ?? '',
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ]);

        $tokens = [];

        if ($request->target_type === 'specific') {
            $user = User::find($request->target_id);
            if ($user?->fcm_token) {
                $tokens = [$user->fcm_token];
            }
        } elseif ($request->target_type === 'all') {
            $tokens = User::whereNotNull('fcm_token')->where('status', 'active')->pluck('fcm_token')->toArray();
        } else {
            $roleMap = ['customers' => 'customer', 'vendors' => 'vendor_owner', 'deliverymen' => 'deliveryman'];
            $role = $roleMap[$request->target_type] ?? null;
            if ($role) {
                $tokens = User::whereHas('role', fn($q) => $q->where('slug', $role))
                    ->whereNotNull('fcm_token')
                    ->pluck('fcm_token')
                    ->toArray();
            }
        }

        $sentCount = 0;
        if (!empty($tokens)) {
            foreach (array_chunk($tokens, 500) as $chunk) {
                $ok = $this->notificationService->sendPush($chunk, $request->title, $request->body, $data);
                if ($ok) $sentCount += count($chunk);
            }
        }

        PushNotificationLog::create([
            'title'       => $request->title,
            'body'        => $request->body,
            'data'        => $data,
            'target_type' => $request->target_type,
            'target_id'   => $request->target_id,
            'deep_link'   => $request->deep_link,
            'sent_count'  => $sentCount ?: count($tokens),
            'sent_by'     => auth()->id(),
        ]);

        return back()->with('success', 'Notification sent to ' . count($tokens) . ' device(s).');
    }

    public function delete(PushNotificationLog $log)
    {
        $log->delete();
        return back()->with('success', 'Notification deleted.');
    }

    public function resend(PushNotificationLog $log)
    {
        $data   = $log->data ?? ['click_action' => 'FLUTTER_NOTIFICATION_CLICK'];
        $tokens = [];

        if ($log->target_type === 'specific') {
            $user = User::find($log->target_id);
            if ($user?->fcm_token) $tokens = [$user->fcm_token];
        } elseif ($log->target_type === 'all') {
            $tokens = User::whereNotNull('fcm_token')->where('status', 'active')->pluck('fcm_token')->toArray();
        } else {
            $roleMap = ['customers' => 'customer', 'vendors' => 'vendor_owner', 'deliverymen' => 'deliveryman'];
            $role = $roleMap[$log->target_type] ?? null;
            if ($role) {
                $tokens = User::whereHas('role', fn($q) => $q->where('slug', $role))
                    ->whereNotNull('fcm_token')->pluck('fcm_token')->toArray();
            }
        }

        foreach (array_chunk($tokens, 500) as $chunk) {
            $this->notificationService->sendPush($chunk, $log->title, $log->body, $data);
        }

        PushNotificationLog::create([
            'title'       => $log->title,
            'body'        => $log->body,
            'data'        => $data,
            'target_type' => $log->target_type,
            'target_id'   => $log->target_id,
            'deep_link'   => $log->deep_link,
            'sent_count'  => count($tokens),
            'sent_by'     => auth()->id(),
        ]);

        return back()->with('success', 'Notification resent to ' . count($tokens) . ' device(s).');
    }
}
