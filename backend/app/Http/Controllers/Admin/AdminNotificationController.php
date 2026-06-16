<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PushNotificationLog;
use App\Models\OrderNotificationTemplate;
use App\Services\Notification\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminNotificationController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    public function index()
    {
        $logs  = PushNotificationLog::with('sentBy')->latest()->paginate(20);
        $stats = [
            'total'      => PushNotificationLog::count(),
            'today'      => PushNotificationLog::whereDate('created_at', today())->count(),
            'with_token' => User::whereNotNull('fcm_token')->count(),
        ];
        return view('admin.notifications.index', compact('logs', 'stats'));
    }

    // ── Order Status Notification Templates ───────────────────────────────────

    public function templates(Request $request)
    {
        $moduleSlug = $request->get('module'); // null = global
        $statuses   = ['pending','confirmed','preparing','ready_for_pickup','out_for_delivery','delivered','cancelled','refunded','failed'];

        // Get all templates for this module (or global)
        $existing = OrderNotificationTemplate::where('module_slug', $moduleSlug ?: null)
            ->get()->keyBy('status');

        // Also get global defaults for display when module has no override
        $globals = OrderNotificationTemplate::whereNull('module_slug')
            ->get()->keyBy('status');

        $modules = \App\Models\Module::orderBy('name')->get(['slug','name']);

        return view('admin.notifications.templates', compact('statuses','existing','globals','modules','moduleSlug'));
    }

    public function saveTemplates(Request $request)
    {
        $moduleSlug = $request->input('module_slug') ?: null;
        $statuses   = ['pending','confirmed','preparing','ready_for_pickup','out_for_delivery','delivered','cancelled','refunded','failed'];

        foreach ($statuses as $status) {
            $title = trim($request->input("title_{$status}", ''));
            $body  = trim($request->input("body_{$status}", ''));

            if ($title === '' && $body === '') {
                // Delete override for this status (revert to global)
                OrderNotificationTemplate::where('module_slug', $moduleSlug)
                    ->where('status', $status)->delete();
                continue;
            }

            OrderNotificationTemplate::updateOrCreate(
                ['module_slug' => $moduleSlug, 'status' => $status],
                ['title' => $title, 'body' => $body]
            );
        }

        // Clear cache for this module
        OrderNotificationTemplate::clearCache($moduleSlug);

        $label = $moduleSlug ? ucfirst($moduleSlug) : 'Global';
        return back()->with('success', "{$label} notification templates saved.");
    }

    public function send(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'body'        => 'required|string',
            'target_type' => 'required|in:all,customers,vendors,deliverymen,specific',
            'target_id'   => 'required_if:target_type,specific|nullable|exists:users,id',
            'banner'      => 'nullable|image|max:2048',
            'deep_link'   => 'nullable|string|max:255',
        ]);

        $imageUrl = null;
        if ($request->hasFile('banner')) {
            $path = $request->file('banner')->store('notifications', 'public');
            $imageUrl = asset('storage/' . $path);
        }

        $deepLink = $request->deep_link;
        $data = [];
        if ($deepLink) $data['deep_link'] = $deepLink;

        $sentCount = 0;

        if ($request->target_type === 'specific') {
            $user = User::find($request->target_id);
            if ($user?->fcm_token) {
                $ok = $this->notificationService->sendPush([$user->fcm_token], $request->title, $request->body, $data, $imageUrl);
                $sentCount = $ok ? 1 : 0;
            }
        } elseif ($request->target_type === 'all') {
            $tokens = User::whereNotNull('fcm_token')->where('status', 'active')->pluck('fcm_token')->toArray();
            $sentCount = count($tokens);
            $this->notificationService->sendPush($tokens, $request->title, $request->body, $data, $imageUrl);
        } else {
            $roleMap = ['customers' => 'customer', 'vendors' => 'vendor_owner', 'deliverymen' => 'deliveryman'];
            $role    = $roleMap[$request->target_type];
            $tokens  = User::whereHas('role', fn($q) => $q->where('slug', $role))
                ->whereNotNull('fcm_token')
                ->pluck('fcm_token')
                ->toArray();
            $sentCount = count($tokens);
            $this->notificationService->sendPush($tokens, $request->title, $request->body, $data, $imageUrl);
        }

        PushNotificationLog::create([
            'title'       => $request->title,
            'body'        => $request->body,
            'image_url'   => $imageUrl,
            'deep_link'   => $deepLink,
            'target_type' => $request->target_type,
            'target_id'   => $request->target_type === 'specific' ? $request->target_id : null,
            'sent_count'  => $sentCount,
            'sent_by'     => auth()->id(),
        ]);

        return back()->with('success', "Notification sent to {$sentCount} device(s).");
    }


    public function bulkDestroy(Request $request)
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer|exists:push_notification_logs,id']);
        $logs = PushNotificationLog::whereIn('id', $request->ids)->get();
        foreach ($logs as $log) {
            if ($log->image_url) {
                $path = str_replace(asset('storage/'), '', $log->image_url);
                Storage::disk('public')->delete($path);
            }
            $log->delete();
        }
        return back()->with('success', count($request->ids) . ' notification(s) deleted.');
    }

    public function destroy(int $id)
    {
        $log = PushNotificationLog::findOrFail($id);
        if ($log->image_url) {
            $path = str_replace(asset('storage/'), '', $log->image_url);
            Storage::disk('public')->delete($path);
        }
        $log->delete();
        return back()->with('success', 'Notification deleted.');
    }

    public function searchUsers(Request $request)
    {
        $q = $request->get('q', '');
        $users = User::where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%")
                      ->orWhere('phone', 'like', "%{$q}%");
            })
            ->whereNotNull('fcm_token')
            ->select('id', 'name', 'email', 'phone')
            ->limit(10)
            ->get();

        return response()->json($users);
    }
}
