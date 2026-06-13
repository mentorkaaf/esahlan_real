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
        $logs = PushNotificationLog::latest()->paginate(20);
        return view('admin.notifications.index', compact('logs'));
    }

    public function send(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'body'        => 'required|string',
            'target_type' => 'required|in:all,customers,vendors,deliverymen,specific',
            'target_id'   => 'required_if:target_type,specific|exists:users,id',
        ]);

        if ($request->target_type === 'specific') {
            $user = User::find($request->target_id);
            if ($user?->fcm_token) {
                $this->notificationService->sendPush(
                    [$user->fcm_token],
                    $request->title,
                    $request->body,
                    $request->data ?? []
                );
            }
        } elseif ($request->target_type === 'all') {
            $this->notificationService->sendBroadcast($request->title, $request->body, $request->data ?? []);
        } else {
            $roleMap = ['customers' => 'customer', 'vendors' => 'vendor_owner', 'deliverymen' => 'deliveryman'];
            $role = $roleMap[$request->target_type];
            $tokens = User::whereHas('role', fn($q) => $q->where('slug', $role))
                ->whereNotNull('fcm_token')
                ->pluck('fcm_token')
                ->toArray();
            $this->notificationService->sendPush($tokens, $request->title, $request->body, $request->data ?? []);
        }

        PushNotificationLog::create([
            'title'       => $request->title,
            'body'        => $request->body,
            'data'        => $request->data,
            'target_type' => $request->target_type,
            'target_id'   => $request->target_id,
            'sent_by'     => auth()->id(),
        ]);

        return back()->with('success', 'Notification sent successfully.');
    }
}
