<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deliveryman;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Wallet;
use App\Services\FcmService;
use App\Services\LoyaltyService;
use App\Services\AffiliateService;
use App\Services\GamificationService;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminOrderController extends Controller
{
    // All known modules in display order
    const MODULE_META = [
        'efood'     => ['label' => 'eFood',      'icon' => 'fa-utensils',      'color' => '#FF8A00'],
        'eshop'     => ['label' => 'eShop',      'icon' => 'fa-shopping-bag',  'color' => '#8B5CF6'],
        'eparcel'   => ['label' => 'eParcel',    'icon' => 'fa-box',           'color' => '#3B82F6'],
        'elaundry'  => ['label' => 'eLaundry',   'icon' => 'fa-tshirt',        'color' => '#06B6D4'],
        'emoving'   => ['label' => 'eMoving',    'icon' => 'fa-truck-moving',  'color' => '#10B981'],
        'eticket'   => ['label' => 'eTicket',    'icon' => 'fa-ticket-alt',    'color' => '#F59E0B'],
        'ehealth'   => ['label' => 'eHealth',    'icon' => 'fa-user-md',       'color' => '#EF4444'],
        'edata'     => ['label' => 'eData',      'icon' => 'fa-wifi',          'color' => '#6366F1'],
        'erent'     => ['label' => 'eRent',      'icon' => 'fa-home',          'color' => '#059669'],
        'egrocery'  => ['label' => 'eGrocery',   'icon' => 'fa-carrot',        'color' => '#84CC16'],
        'wholesale' => ['label' => 'Wholesale',  'icon' => 'fa-warehouse',     'color' => '#F97316'],
    ];

    public function index(Request $request)
    {
        $search    = $request->search;
        $status    = $request->status;
        $dateFrom  = $request->date_from;
        $dateTo    = $request->date_to;
        $module    = $request->module; // optional single-module filter

        /** @var \App\Models\User $authUser */
        $authUser = auth()->user();

        // Employees are scoped to their assigned modules only.
        $employeeModuleSlugs = $authUser->isEmployee()
            ? $authUser->managedModules()->pluck('slug')->all()
            : null;

        // Base query with filters
        $baseQuery = Order::with(['user', 'vendor', 'deliveryman'])
            ->when($status,   fn($q) => $q->where('status', $status))
            ->when($dateFrom, fn($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($search,   fn($q) => $q->where(function ($q2) use ($search) {
                $q2->where('order_number', 'like', "%{$search}%")
                   ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"));
            }))
            ->when($employeeModuleSlugs, fn($q) => $q->whereIn('module_slug', $employeeModuleSlugs))
            ->latest();

        // If a single module is requested, just paginate that
        if ($module) {
            // Employees cannot bypass scope via URL parameter
            if ($employeeModuleSlugs && !in_array($module, $employeeModuleSlugs)) {
                abort(403, 'You are not assigned to this module.');
            }
            $orders = $baseQuery->where('module_slug', $module)->paginate(20);
            $moduleGroups = null;
        } else {
            // Get all orders and group by module_slug for sectioned display
            $allOrders    = $baseQuery->get();
            $orders       = null;
            $moduleGroups = $allOrders->groupBy(fn($o) => $o->module_slug ?? 'other');
        }

        $statusCounts = Order::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')->pluck('count', 'status');

        $moduleCounts = Order::selectRaw('module_slug, COUNT(*) as count')
            ->groupBy('module_slug')->pluck('count', 'module_slug');

        $exchangeOrders = DB::table('exchange_orders')
            ->join('users', 'users.id', '=', 'exchange_orders.user_id')
            ->select('exchange_orders.*', 'users.name as user_name', 'users.phone as user_phone')
            ->when($search, fn($q) => $q->where(function ($q2) use ($search) {
                $q2->where('reference', 'like', "%{$search}%")
                   ->orWhere('recipient_phone', 'like', "%{$search}%")
                   ->orWhere('users.name', 'like', "%{$search}%");
            }))
            ->when($status, fn($q) => $q->where('exchange_orders.status', $status))
            ->orderByDesc('exchange_orders.created_at')
            ->limit(100)
            ->get();

        $moduleMeta = self::MODULE_META;

        // Available drivers for map
        $availableDrivers = Deliveryman::with('user:id,name,phone')
            ->where('is_approved', true)
            ->whereIn('status', ['available', 'busy'])
            ->whereNotNull('latitude')
            ->get(['id', 'user_id', 'vehicle_type', 'status', 'latitude', 'longitude', 'rating']);

        return view('admin.orders.index', compact(
            'orders', 'moduleGroups', 'statusCounts', 'moduleCounts',
            'exchangeOrders', 'moduleMeta', 'availableDrivers'
        ));
    }

    private function authorizeOrderAccess(Order $order): void
    {
        $user = auth()->user();
        if ($user->isEmployee() && !$user->canManageModule($order->module_slug ?? '')) {
            abort(403, 'You are not assigned to manage orders for this module.');
        }
    }

    public function show(Order $order)
    {
        $this->authorizeOrderAccess($order);
        try {
            $order->load(['user.district', 'vendor', 'deliveryman', 'items.product', 'statusHistory']);
        } catch (\Throwable $e) {
            $order->load(['user.district', 'vendor', 'deliveryman', 'items.product']);
        }
        $deliverymen = Deliveryman::where('is_approved', true)->where('status', 'available')->with('user')->get();
        return view('admin.orders.show', compact('order', 'deliverymen'));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $this->authorizeOrderAccess($order);
        $request->validate([
            'status' => 'required|in:pending,confirmed,preparing,ready_for_pickup,out_for_delivery,delivered,cancelled,refunded,failed',
            'note'   => 'nullable|string',
        ]);

        DB::transaction(function () use ($order, $request) {
            $updateData = ['status' => $request->status];

            if ($request->status === 'delivered') $updateData['delivered_at'] = now();
            if ($request->status === 'confirmed')  $updateData['confirmed_at'] = now();

            $order->update($updateData);

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => $request->status,
                'note'       => $request->note ?? 'Status updated by admin',
                'changed_by' => auth()->id(),
            ]);

            // Credit loyalty points + referral + affiliate commissions on delivery or completion
            // Dispatched with 3-second delay so reward notification arrives after the delivery notification
            if (in_array($request->status, ['delivered', 'completed', 'boarded'])) {
                $orderId     = $order->id;
                $orderUserId = $order->user_id;
                $orderTotal  = (float) $order->total_amount;
                \Illuminate\Support\Facades\DB::afterCommit(function () use ($orderId, $orderUserId, $orderTotal) {
                    dispatch(function () use ($orderId, $orderUserId, $orderTotal) {
                        try { LoyaltyService::creditOrderPoints($orderId); } catch (\Throwable $e) { \Log::error('creditOrderPoints: '.$e->getMessage()); }
                        try { ReferralService::processFirstOrderReward($orderUserId, $orderId, $orderTotal); } catch (\Throwable $e) { \Log::error('processFirstOrderReward: '.$e->getMessage()); }
                        try { AffiliateService::processOrderCommission($orderId); } catch (\Throwable $e) { \Log::error('processOrderCommission: '.$e->getMessage()); }
                        try { GamificationService::recordOrderAndCheckStreak($orderUserId, $orderId); } catch (\Throwable $e) { \Log::error('recordOrderAndCheckStreak: '.$e->getMessage()); }
                        try { GamificationService::checkAllBadges($orderUserId); } catch (\Throwable $e) { \Log::error('checkAllBadges: '.$e->getMessage()); }
                    })->delay(now()->addSeconds(3));
                });
            }

            // Credit vendor wallet + settle commission on delivery
            if ($request->status === 'delivered') {
                $commissionRow = DB::table('commissions')
                    ->where('order_id', $order->id)
                    ->where('status', 'pending')
                    ->first();

                $effectiveVendorId = $order->vendor_id ?? $commissionRow?->vendor_id;

                if ($effectiveVendorId) {
                    $vendorEarning = $commissionRow
                        ? (float) $commissionRow->vendor_earning
                        : max(0, (float) $order->subtotal - (float) ($order->commission ?? 0));

                    if ($vendorEarning > 0) {
                        $vendorWallet = Wallet::getOrCreateFor('App\\Models\\Vendor', $effectiveVendorId);
                        $vendorWallet->credit($vendorEarning, "Order #{$order->order_number} earning", 'App\\Models\\Order', $order->id);
                    }
                }

                DB::table('commissions')
                    ->where('order_id', $order->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'settled', 'settled_at' => now()]);
            }
        });

        // ── Push notification ─────────────────────────────────────────────
        $order->load('user');
        $fcmToken = $order->user?->fcm_token;
        \Log::info('[FCM] Order status change', [
            'order_id'    => $order->id,
            'status'      => $request->status,
            'user_id'     => $order->user?->id,
            'has_token'   => !empty($fcmToken),
            'module_slug' => $order->module_slug,
        ]);
        if ($fcmToken) {
            try {
                $sent = FcmService::sendOrderUpdate(
                    $fcmToken,
                    $order->order_number,
                    $request->status,
                    $order->id,
                    $order->module_slug,
                );
                \Log::info('[FCM] sendOrderUpdate result: ' . ($sent ? 'OK' : 'FAILED'));
            } catch (\Throwable $e) {
                \Log::warning('[FCM] Notification exception: ' . $e->getMessage());
            }
        }

        // ── Notify assigned driver ───────────────────────────────────────
        if ($order->deliveryman_id) {
            try {
                $order->load('deliveryman.user');
                $driverToken = $order->deliveryman?->fcm_token ?? $order->deliveryman?->user?->fcm_token;
                if ($driverToken) {
                    FcmService::sendDriverOrderUpdate($driverToken, $order->order_number, $request->status, $order->id, $order->module_slug);
                }
            } catch (\Throwable) {}
        }

        // ── Notify nearby drivers when order is confirmed (proximity-based) ──
        if (in_array($request->status, ['confirmed', 'ready_for_pickup']) && !$order->deliveryman_id) {
            try {
                // Get pickup coordinates — varies by module
                $order->load('vendor');
                $pickupLat = 0.0;
                $pickupLng = 0.0;

                $module = $order->module_slug ?? '';

                if (in_array($module, ['efood', 'egrocery', 'eshop', 'elaundry'])) {
                    // Standard modules: pickup = vendor location, fallback to vendor's district
                    $pickupLat = (float) ($order->vendor?->latitude ?? 0);
                    $pickupLng = (float) ($order->vendor?->longitude ?? 0);
                    if (!$pickupLat || !$pickupLng) {
                        $vendorDistrictId = $order->vendor?->district_id ?? $order->district_id;
                        $dist = DB::table('districts')->find($vendorDistrictId);
                        $pickupLat = (float) ($dist->latitude ?? 0);
                        $pickupLng = (float) ($dist->longitude ?? 0);
                    }
                } elseif ($module === 'eparcel') {
                    // eParcel: pickup = sender district (stored in order.note JSON)
                    $noteData = is_array($order->note) ? $order->note : json_decode($order->note ?? '{}', true);
                    $senderDistrictId = $noteData['pickup']['district_id'] ?? null;
                    if ($senderDistrictId) {
                        $dist = DB::table('districts')->find($senderDistrictId);
                        $pickupLat = (float) ($dist->latitude ?? 0);
                        $pickupLng = (float) ($dist->longitude ?? 0);
                    }
                } elseif ($module === 'emoving') {
                    // eMoving: pickup = from_district
                    $noteData = is_array($order->note) ? $order->note : json_decode($order->note ?? '{}', true);
                    $fromDistName = $noteData['from_district'] ?? null;
                    if ($fromDistName) {
                        $dist = DB::table('districts')->where('name', $fromDistName)->first();
                        $pickupLat = (float) ($dist->latitude ?? 0);
                        $pickupLng = (float) ($dist->longitude ?? 0);
                    }
                } else {
                    // Any other module: try vendor first, then order's district
                    $pickupLat = (float) ($order->vendor?->latitude ?? 0);
                    $pickupLng = (float) ($order->vendor?->longitude ?? 0);
                    if (!$pickupLat) {
                        $dist = DB::table('districts')->find($order->district_id);
                        $pickupLat = (float) ($dist->latitude ?? 0);
                        $pickupLng = (float) ($dist->longitude ?? 0);
                    }
                }

                $radiusKm = (float) \App\Helpers\AppSettings::get('driver_notification_radius_km', 2);
                // Location is considered fresh if updated within 10 minutes
                $locationFreshCutoff = now()->subMinutes(10);

                $onlineDrivers = Deliveryman::where('is_approved', true)
                    ->where('is_online', true)
                    ->whereNotNull('fcm_token')
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->where('last_location_at', '>=', $locationFreshCutoff)
                    ->when($pickupLat && $pickupLng, function ($q) use ($pickupLat, $pickupLng, $radiusKm) {
                        $q->whereRaw(
                            '(6371 * acos(
                                cos(radians(?)) * cos(radians(latitude))
                                * cos(radians(longitude) - radians(?))
                                + sin(radians(?)) * sin(radians(latitude))
                            )) <= ?',
                            [$pickupLat, $pickupLng, $pickupLat, $radiusKm]
                        );
                    })
                    ->pluck('fcm_token')
                    ->toArray();

                // No nearby drivers with fresh location — skip notification
                // (do NOT fall back to all drivers; stale/unknown location = unreliable proximity)
                if (!empty($onlineDrivers)) {
                    $tpl = \App\Models\OrderNotificationTemplate::resolve($request->status, $order->module_slug, 'driver');
                    $title = $tpl['title'];
                    $body = str_replace('{order_number}', $order->order_number, $tpl['body']);
                    $sent = FcmService::sendToTokens($onlineDrivers, $title, $body, [
                        'type'         => 'new_order_available',
                        'order_id'     => (string) $order->id,
                        'order_number' => $order->order_number,
                        'deep_link'    => '/orders',
                    ], null, 'esahlan_driver_v1');
                    \Log::info('[FCM] Nearby driver notifications sent', [
                        'order_id'       => $order->id,
                        'drivers_found'  => count($onlineDrivers),
                        'sent'           => $sent,
                        'pickup_lat'     => $pickupLat,
                        'pickup_lng'     => $pickupLng,
                        'radius_km'      => $radiusKm,
                    ]);
                }
            } catch (\Throwable) {}
        }

        return back()->with('success', 'Order status updated to ' . $request->status);
    }

    public function assignDeliveryman(Request $request, Order $order)
    {
        $this->authorizeOrderAccess($order);
        $request->validate(['deliveryman_id' => 'required|exists:deliverymen,id']);

        DB::transaction(function () use ($order, $request) {
            $order->update([
                'deliveryman_id' => $request->deliveryman_id,
                'dispatched_at'  => now(),
            ]);

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => $order->status,
                'note'       => 'Deliveryman manually assigned by admin',
                'actor_id'   => auth()->id(),
                'actor_type' => 'App\\Models\\User',
            ]);

            // Update deliveryman status
            Deliveryman::where('id', $request->deliveryman_id)->update(['status' => 'busy']);
        });

        // Notify the customer
        $order->load('user');
        $fcmToken = $order->user?->fcm_token;
        if ($fcmToken) {
            try {
                FcmService::sendOrderUpdate(
                    $fcmToken,
                    $order->order_number,
                    'out_for_delivery',
                    $order->id,
                    $order->module_slug,
                );
            } catch (\Throwable $e) {
                \Log::warning('[FCM] Assign notification failed: ' . $e->getMessage());
            }
        }

        return back()->with('success', 'Deliveryman assigned successfully.');
    }

    public function unassignDriver(Order $order)
    {
        $oldDriverId = $order->deliveryman_id;

        DB::transaction(function () use ($order, $oldDriverId) {
            $order->update(['deliveryman_id' => null, 'dispatched_at' => null]);

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => $order->status,
                'note'       => 'Driver removed by admin',
                'changed_by' => auth()->id(),
            ]);

            if ($oldDriverId) {
                $hasOther = Order::where('deliveryman_id', $oldDriverId)
                    ->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery'])
                    ->exists();
                if (!$hasOther) {
                    Deliveryman::where('id', $oldDriverId)->update(['status' => 'available', 'is_available' => true]);
                }
            }
        });

        return back()->with('success', 'Driver removed. Order is now unassigned.');
    }

    public function reassignDriver(Request $request, Order $order)
    {
        $request->validate(['deliveryman_id' => 'required|exists:deliverymen,id']);
        $oldDriverId = $order->deliveryman_id;
        $newDriverId = $request->deliveryman_id;

        DB::transaction(function () use ($order, $oldDriverId, $newDriverId) {
            $order->update(['deliveryman_id' => $newDriverId, 'dispatched_at' => now()]);

            $newDriver = Deliveryman::with('user')->find($newDriverId);
            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => $order->status,
                'note'       => 'Reassigned to ' . ($newDriver?->user?->name ?? 'driver'),
                'changed_by' => auth()->id(),
            ]);

            // Free old driver if no other active orders
            if ($oldDriverId) {
                $hasOther = Order::where('deliveryman_id', $oldDriverId)
                    ->where('id', '!=', $order->id)
                    ->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery'])
                    ->exists();
                if (!$hasOther) {
                    Deliveryman::where('id', $oldDriverId)->update(['status' => 'available', 'is_available' => true]);
                }
            }

            // Mark new driver as busy
            Deliveryman::where('id', $newDriverId)->update(['status' => 'busy', 'is_available' => false]);
        });

        // Notify new driver
        try {
            $newDm = Deliveryman::with('user')->find($newDriverId);
            $token = $newDm?->fcm_token ?? $newDm?->user?->fcm_token;
            if ($token) {
                FcmService::sendOrderUpdate($token, $order->order_number, 'out_for_delivery', $order->id, $order->module_slug);
            }
        } catch (\Throwable) {}

        return back()->with('success', 'Order reassigned to new driver.');
    }

    public function pollNew(Request $request)
    {
        $since = $request->query('since'); // Unix timestamp (seconds)
        if (!$since || !is_numeric($since)) {
            return response()->json(['orders' => [], 'ts' => now()->timestamp]);
        }

        $user = auth()->user();
        $sinceDate = \Carbon\Carbon::createFromTimestamp((int) $since);

        $query = Order::with('user')
            ->where('created_at', '>', $sinceDate)
            ->latest();

        if ($user->isEmployee()) {
            $slugs = $user->managedModules()->pluck('slug')->all();
            $query->whereIn('module_slug', $slugs);
        }

        $orders = $query->limit(20)->get()->map(fn($o) => [
            'id'           => $o->id,
            'order_number' => $o->order_number ?? '#' . $o->id,
            'module'       => $o->module_slug ?? 'order',
            'customer'     => $o->user?->name ?? 'Customer',
            'total'        => number_format($o->total_amount ?? 0, 2),
            'url'          => route('admin.orders.show', $o->id),
        ]);

        // Include exchange orders (not in orders table) — full admins only
        $exchange = [];
        if (!$user->isEmployee()) {
            $exchange = \DB::table('exchange_orders')
                ->join('users', 'users.id', '=', 'exchange_orders.user_id')
                ->select('exchange_orders.id', 'exchange_orders.created_at', 'exchange_orders.sent_amount',
                         'exchange_orders.from_wallet', 'exchange_orders.to_wallet', 'exchange_orders.reference',
                         'users.name as user_name')
                ->where('exchange_orders.created_at', '>', $sinceDate)
                ->orderByDesc('exchange_orders.created_at')
                ->limit(10)->get()
                ->map(fn($e) => [
                    'id'           => 'exc_' . $e->id,
                    'order_number' => $e->reference,
                    'module'       => 'eexchange',
                    'customer'     => $e->user_name ?? 'Customer',
                    'total'        => number_format($e->sent_amount ?? 0, 2),
                    'url'          => route('admin.exchange.show', $e->id),
                ])->all();
        }

        // Include eLearning enrollments (not in orders table) — full admins only
        $elearning = [];
        if (!$user->isEmployee()) {
            $elearning = \DB::table('el_enrollments')
                ->join('el_courses', 'el_courses.id', '=', 'el_enrollments.course_id')
                ->join('users', 'users.id', '=', 'el_enrollments.user_id')
                ->select('el_enrollments.id', 'el_enrollments.created_at', 'el_enrollments.amount_paid',
                         'el_courses.title', 'users.name as user_name')
                ->where('el_enrollments.created_at', '>', $sinceDate)
                ->orderByDesc('el_enrollments.created_at')
                ->limit(10)->get()
                ->map(fn($e) => [
                    'id'           => 'el_' . $e->id,
                    'order_number' => 'Course Enrollment',
                    'module'       => 'elearning',
                    'customer'     => $e->user_name ?? 'Student',
                    'total'        => number_format($e->amount_paid ?? 0, 2),
                    'url'          => route('admin.elearning.dashboard'),
                ])->all();
        }

        $all = $orders->concat($exchange)->concat($elearning)->values();

        return response()->json(['orders' => $all, 'ts' => now()->timestamp]);
    }

    public function bulkAction(Request $request)
    {
        $rules = [
            'action' => 'required|in:status,delete',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer|exists:orders,id',
        ];
        if ($request->action === 'status') {
            $rules['status'] = 'required|in:pending,confirmed,preparing,ready_for_pickup,out_for_delivery,delivered,cancelled,refunded,failed';
        }
        $request->validate($rules);

        $ids = $request->ids;

        if ($request->action === 'delete') {
            Order::whereIn('id', $ids)->delete();
            return back()->with('success', count($ids) . ' order(s) deleted.');
        }

        // Bulk status update
        Order::whereIn('id', $ids)->update(['status' => $request->status]);

        // Send notifications
        $orders = Order::with('user')->whereIn('id', $ids)->get();
        foreach ($orders as $order) {
            if ($order->user?->fcm_token) {
                try {
                    FcmService::sendOrderUpdate($order->user->fcm_token, $order->order_number ?? '#'.$order->id, $request->status, $order->id, $order->module_slug);
                } catch (\Throwable $e) {}
            }
        }

        return back()->with('success', count($ids) . ' order(s) updated to ' . $request->status . '.');
    }

}