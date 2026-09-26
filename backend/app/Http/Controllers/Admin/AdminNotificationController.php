<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PushNotificationLog;
use App\Models\NotificationLog;
use App\Models\OrderNotificationTemplate;
use App\Models\DiscountCampaign;
use App\Services\FcmService;
use App\Services\Notification\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
            'with_token' => User::whereNotNull('fcm_token')->count()
                          + \App\Models\Vendor::whereNotNull('vendor_fcm_token')->count(),
        ];
        return view('admin.notifications.index', compact('logs', 'stats'));
    }

    // ── Order Status Notification Templates ───────────────────────────────────

    public function templates(Request $request)
    {
        $moduleSlug = $request->get('module');
        $target     = $request->get('target', 'customer');
        $statuses   = ['pending','confirmed','preparing','ready_for_pickup','out_for_delivery','delivered','cancelled','refunded','failed'];

        $existing = OrderNotificationTemplate::where('module_slug', $moduleSlug ?: null)
            ->where('target', $target)
            ->get()->keyBy('status');

        $globals = OrderNotificationTemplate::whereNull('module_slug')
            ->where('target', $target)
            ->get()->keyBy('status');

        $modules = \App\Models\Module::orderBy('name')->get(['slug','name']);

        return view('admin.notifications.templates', compact('statuses','existing','globals','modules','moduleSlug','target'));
    }

    public function saveTemplates(Request $request)
    {
        $moduleSlug = $request->input('module_slug') ?: null;
        $target     = $request->input('target', 'customer');
        $statuses   = ['pending','confirmed','preparing','ready_for_pickup','out_for_delivery','delivered','cancelled','refunded','failed'];

        foreach ($statuses as $status) {
            $title = trim($request->input("title_{$status}", ''));
            $body  = trim($request->input("body_{$status}", ''));

            if ($title === '' && $body === '') {
                OrderNotificationTemplate::where('module_slug', $moduleSlug)
                    ->where('status', $status)->where('target', $target)->delete();
                continue;
            }

            OrderNotificationTemplate::updateOrCreate(
                ['module_slug' => $moduleSlug, 'status' => $status, 'target' => $target],
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

        // Create the push notification log first (we need its ID for per-delivery tracking)
        $pushLog = PushNotificationLog::create([
            'title'       => $request->title,
            'body'        => $request->body,
            'image_url'   => $imageUrl,
            'deep_link'   => $deepLink,
            'target_type' => $request->target_type,
            'target_id'   => $request->target_type === 'specific' ? $request->target_id : null,
            'sent_count'  => 0,
            'sent_by'     => auth()->id(),
        ]);

        $sentCount = $this->_sendToTargets(
            $request->target_type,
            $request->target_id,
            $request->title,
            $request->body,
            $data,
            $imageUrl,
            $pushLog->id,
        );

        $pushLog->update(['sent_count' => $sentCount]);

        return back()->with('success', "Notification sent to {$sentCount} device(s).");
    }

    // ── Re-send to users who did NOT open ────────────────────────────────────
    public function resend(int $id)
    {
        $pushLog = PushNotificationLog::findOrFail($id);
        $tokens  = NotificationLog::unopenedTokensFor($id);

        if (empty($tokens)) {
            return back()->with('info', 'Everyone already opened this notification.');
        }

        $data = [];
        if ($pushLog->deep_link) $data['deep_link'] = $pushLog->deep_link;

        $sent = 0;
        foreach ($tokens as $token) {
            $ok = FcmService::sendToToken(
                $token,
                $pushLog->title,
                $pushLog->body,
                $data,
                $pushLog->image_url,
                'esahlan_high_v3',
                $pushLog->id,
            );
            if ($ok) $sent++;
        }

        return back()->with('success', "Re-sent to {$sent} user(s) who hadn't opened.");
    }

    // ── Open-rate stats for a single notification ─────────────────────────────
    public function stats(int $id)
    {
        $pushLog = PushNotificationLog::findOrFail($id);
        $stats   = NotificationLog::statsFor($id);

        $rows = NotificationLog::where('push_notification_id', $id)
            ->with([
                'user:id,name,phone,email',
                'vendor:id,name,phone,email',
                'deliveryman:id,user_id',
                'deliveryman.user:id,name,phone,email',
            ])
            ->latest()
            ->get();

        return view('admin.notifications.stats', compact('pushLog', 'stats', 'rows'));
    }

    // ── Internal: send to all targets + log per-user ──────────────────────────
    private function _sendToTargets(
        string  $targetType,
        ?int    $targetId,
        string  $title,
        string  $body,
        array   $data,
        ?string $imageUrl,
        int     $pushLogId,
    ): int {
        $sent = 0;

        if ($targetType === 'specific') {
            $user = User::find($targetId);
            if ($user?->fcm_token) {
                $ok = FcmService::sendToToken(
                    $user->fcm_token, $title, $body, $data, $imageUrl,
                    'esahlan_high_v3', $pushLogId, $user->id, 'customer'
                );
                if ($ok) $sent++;
            }
            return $sent;
        }

        if ($targetType === 'vendors') {
            $vendors = \App\Models\Vendor::whereNotNull('vendor_fcm_token')->get(['id','vendor_fcm_token']);
            foreach ($vendors as $v) {
                $ok = FcmService::sendToToken(
                    $v->vendor_fcm_token, $title, $body, $data, $imageUrl,
                    'esahlan_high_v3', $pushLogId, $v->id, 'vendor'
                );
                if ($ok) $sent++;
            }
            return $sent;
        }

        if ($targetType === 'deliverymen') {
            $drivers = \DB::table('deliverymen')->whereNotNull('fcm_token')->get(['id','fcm_token']);
            foreach ($drivers as $d) {
                $ok = FcmService::sendToToken(
                    $d->fcm_token, $title, $body, $data, $imageUrl,
                    'esahlan_high_v3', $pushLogId, $d->id, 'driver'
                );
                if ($ok) $sent++;
            }
            return $sent;
        }

        // all or customers
        $query = User::whereNotNull('fcm_token')->where('status', 'active');
        if ($targetType === 'customers') {
            $query->whereHas('role', fn($q) => $q->where('slug', 'customer'));
        }
        $users = $query->get(['id','fcm_token']);
        foreach ($users as $u) {
            $ok = FcmService::sendToToken(
                $u->fcm_token, $title, $body, $data, $imageUrl,
                'esahlan_high_v3', $pushLogId, $u->id, 'customer'
            );
            if ($ok) $sent++;
        }
        return $sent;
    }


    // ── Discount Campaign Notifications ──────────────────────────────────────

    /**
     * List all discount campaigns with their notification status.
     */
    public function discountCampaigns()
    {
        $now = now();
        $campaigns = DiscountCampaign::with(['vendor:id,name,logo', 'category:id,name'])
            ->orderByDesc('id')
            ->paginate(20);

        $userCount = User::whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->where('status', '!=', 'banned')
            ->count();

        return view('admin.notifications.discount_campaigns', compact('campaigns', 'userCount', 'now'));
    }

    /**
     * Manually send notification for a specific campaign.
     */
    public function sendCampaignNotification(Request $request, int $id)
    {
        $campaign = DiscountCampaign::with(['vendor:id,name,logo', 'category:id,name'])->findOrFail($id);

        if (!$campaign->isLive()) {
            return back()->with('error', 'Campaign is not currently active.');
        }

        $now        = now();
        $vendorName = $campaign->vendor?->name ?? 'Restaurant';
        $catName    = $campaign->category?->name;
        $endsAt     = $campaign->ends_at;
        $logoUrl    = $campaign->vendor?->logo;

        $discountStr = $campaign->discount_type === 'percentage'
            ? "{$campaign->discount_value}% OFF"
            : '$' . number_format($campaign->discount_value, 0) . ' OFF';

        $minutesLeft = (int) $now->diffInMinutes($endsAt, false);
        $urgent      = $minutesLeft <= 120;

        // Override title/body if provided
        $title = trim($request->input('title', ''));
        $body  = trim($request->input('body', ''));

        if ($title === '') {
            $title = $urgent
                ? "⏰ Hurry! {$discountStr} at {$vendorName} — {$minutesLeft} min left!"
                : "🔥 {$discountStr} at {$vendorName}!";
        }
        if ($body === '') {
            $body = $catName
                ? "Get {$discountStr} on {$catName} at {$vendorName}. Ends {$endsAt->format('M j, g:ia')}!"
                : "Get {$discountStr} at {$vendorName}. Ends {$endsAt->format('M j, g:ia')}!";
        }

        $deepLink = '/efood/restaurant/' . $campaign->vendor_id;
        $data = [
            'type'        => 'discount_campaign',
            'campaign_id' => (string) $campaign->id,
            'vendor_id'   => (string) $campaign->vendor_id,
            'module'      => 'efood',
            'deep_link'   => $deepLink,
            'is_urgent'   => $urgent ? '1' : '0',
        ];

        // Create a push log
        $pushLog = PushNotificationLog::create([
            'title'       => $title,
            'body'        => $body,
            'image_url'   => $logoUrl ? cdn_url($logoUrl) : null,
            'deep_link'   => $deepLink,
            'target_type' => 'all',
            'sent_count'  => 0,
            'sent_by'     => auth()->id(),
        ]);

        $users = User::whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->where('status', '!=', 'banned')
            ->select('id', 'fcm_token')
            ->get();

        $sent = 0;
        foreach ($users as $user) {
            // Skip users who already received this campaign notif in the last 2h
            $cacheKey = "camp_manual:{$campaign->id}:{$user->id}";
            if (Cache::has($cacheKey)) continue;

            $ok = FcmService::sendToToken(
                $user->fcm_token,
                $title,
                $body,
                $data,
                $logoUrl ? cdn_url($logoUrl) : null,
                $urgent ? 'esahlan_high_v3' : 'esahlan_promo',
                $pushLog->id,
                $user->id,
                'customer',
            );
            if ($ok) {
                Cache::put($cacheKey, 1, 7200); // 2h
                $sent++;
            }
        }

        $pushLog->update(['sent_count' => $sent]);

        return back()->with('success', "✅ Sent {$sent} notifications for campaign: {$vendorName} {$discountStr}");
    }

    /**
     * Update campaign notification text template + interval.
     */
    public function updateCampaignTemplate(Request $request, int $id)
    {
        $request->validate([
            'notif_title'          => 'nullable|string|max:255',
            'notif_body'           => 'nullable|string|max:500',
            'notif_interval_hours' => 'required|integer|min:1|max:48',
        ]);

        $campaign = DiscountCampaign::findOrFail($id);
        $campaign->update([
            'notif_title'          => $request->notif_title ?: null,
            'notif_body'           => $request->notif_body  ?: null,
            'notif_interval_hours' => (int) $request->notif_interval_hours,
        ]);

        return back()->with('success', "✅ Template saved for {$campaign->vendor?->name}.");
    }

    /**
     * Toggle pause/resume auto-notifications for a campaign.
     */
    public function toggleCampaignPause(int $id)
    {
        $campaign = DiscountCampaign::findOrFail($id);
        $campaign->update(['notif_paused' => !$campaign->notif_paused]);

        $state = $campaign->notif_paused ? '⏸ Paused' : '▶️ Resumed';
        return back()->with('success', "{$state} auto-notifications for {$campaign->vendor?->name}.");
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

    // ── Cart Abandonment Notification Templates ───────────────────────────────

    public function cartTemplates()
    {
        $templates = \Illuminate\Support\Facades\DB::table('cart_notification_templates')
            ->orderBy('module')
            ->orderByRaw("FIELD(stage, '30min', '2h', '24h')")
            ->get();

        return view('admin.notifications.cart_templates', compact('templates'));
    }

    public function updateCartTemplate(Request $request)
    {
        $request->validate([
            'module' => 'required|string',
            'stage'  => 'required|in:30min,2h,24h',
            'title'  => 'required|string|max:255',
            'body'   => 'required|string',
        ]);

        \Illuminate\Support\Facades\DB::table('cart_notification_templates')->updateOrInsert(
            ['module' => $request->module, 'stage' => $request->stage],
            ['title' => $request->title, 'body' => $request->body, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]
        );

        return back()->with('success', 'Template updated successfully.');
    }

    public function toggleCartTemplate(Request $request)
    {
        $request->validate([
            'module'    => 'required|string',
            'stage'     => 'required|in:30min,2h,24h',
            'is_active' => 'required|boolean',
        ]);

        \Illuminate\Support\Facades\DB::table('cart_notification_templates')->updateOrInsert(
            ['module' => $request->module, 'stage' => $request->stage],
            ['is_active' => $request->is_active, 'updated_at' => now(), 'created_at' => now()]
        );

        return response()->json(['success' => true]);
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

    // ══════════════════════════════════════════════════════════════════════════
    // AUTO NOTIFICATIONS — Template management (eTicket flights, future types)
    // ══════════════════════════════════════════════════════════════════════════

    public function autoNotifications()
    {
        $templates = \DB::table('auto_notification_templates')
            ->orderBy('type')
            ->orderBy('label')
            ->get();

        // Per-template: recent send logs
        $logs = \DB::table('auto_notification_logs')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->groupBy('template_slug');

        // eTicket: upcoming flights (for preview)
        $upcomingFlights = \DB::table('flights')
            ->where('status', 'scheduled')
            ->where('available_seats', '>', 0)
            ->whereDate('departure_at', '>=', now()->toDateString())
            ->orderBy('departure_at')
            ->limit(5)
            ->get(['id', 'flight_number', 'from_city', 'to_city', 'from_code', 'to_code', 'departure_at', 'available_seats', 'seat_classes']);

        // Stats
        $stats = [
            'users_with_fcm' => User::whereNotNull('fcm_token')->where('fcm_token', '!=', '')->count(),
        ];

        return view('admin.notifications.auto_notifications', compact('templates', 'logs', 'upcomingFlights', 'stats'));
    }

    public function updateAutoTemplate(Request $request, string $slug)
    {
        $request->validate([
            'title_template'  => 'required|string|max:255',
            'body_template'   => 'required|string|max:1000',
            'title_so'        => 'nullable|string|max:255',
            'body_so'         => 'nullable|string|max:1000',
            'language'        => 'nullable|in:en,so,both',
            'interval_hours'  => 'required|integer|min:1|max:720',
            'send_time'       => 'nullable|date_format:H:i',
        ]);

        \DB::table('auto_notification_templates')
            ->where('slug', $slug)
            ->update([
                'title_template' => $request->title_template,
                'body_template'  => $request->body_template,
                'title_so'       => $request->title_so ?: null,
                'body_so'        => $request->body_so  ?: null,
                'language'       => $request->language  ?? 'en',
                'interval_hours' => (int) $request->interval_hours,
                'send_time'      => $request->send_time ?: null,
                'updated_at'     => now(),
            ]);

        return back()->with('success', 'Template updated successfully.');
    }

    public function toggleAutoTemplate(Request $request, string $slug)
    {
        $current = \DB::table('auto_notification_templates')->where('slug', $slug)->value('is_active');
        \DB::table('auto_notification_templates')
            ->where('slug', $slug)
            ->update(['is_active' => !$current, 'updated_at' => now()]);

        $status = $current ? 'disabled' : 'enabled';
        return response()->json(['success' => true, 'is_active' => !$current, 'message' => "Notification {$status}."]);
    }

    public function sendAutoNow(Request $request, string $slug)
    {
        $template = \DB::table('auto_notification_templates')->where('slug', $slug)->first();
        if (!$template) return back()->with('error', 'Template not found.');

        // Dispatch artisan command in background
        $command = match(true) {
            $slug === 'reengagement_3d'  => 'marketing:reengagement --days=3',
            $slug === 'reengagement_7d'  => 'marketing:reengagement --days=7',
            $slug === 'reengagement_14d' => 'marketing:reengagement --days=14',
            $slug === 'reengagement_30d' => 'marketing:reengagement --days=30',
            in_array($slug, ['lunch_time', 'evening_deals', 'weekend_promo'])
                                         => "marketing:time-based --slug={$slug}",
            $slug === 'new_vendor_district' => 'marketing:new-vendor',
            $slug === 'points_expiry'    => 'marketing:loyalty --type=points_expiry',
            $slug === 'wallet_low'       => 'marketing:loyalty --type=wallet_low',
            $slug === 'eticket_upcoming_flight' => 'eticket:send-flight-notifications',
            default => null,
        };

        if (!$command) return back()->with('error', 'No command registered for this template.');

        try {
            \Artisan::queue($command)->onQueue('default');
            return back()->with('success', "Notification job queued. Will send shortly.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to queue: ' . $e->getMessage());
        }
    }
}
