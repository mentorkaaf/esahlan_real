<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deliveryman;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\FcmService;
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

        return view('admin.orders.index', compact(
            'orders', 'moduleGroups', 'statusCounts', 'moduleCounts',
            'exchangeOrders', 'moduleMeta'
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
            $order->load(['user', 'vendor', 'deliveryman', 'items.product', 'statusHistory']);
        } catch (\Throwable $e) {
            $order->load(['user', 'vendor', 'deliveryman', 'items.product']);
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
        });

        // ── Push notification ─────────────────────────────────────────────
        $order->load('user');
        $fcmToken = $order->user?->fcm_token;
        if ($fcmToken) {
            try {
                FcmService::sendOrderUpdate(
                    $fcmToken,
                    $order->order_number,
                    $request->status,
                    $order->id,
                );
            } catch (\Throwable $e) {
                // Never block the response on FCM failure
                \Log::warning('[FCM] Notification failed: ' . $e->getMessage());
            }
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

        return back()->with('success', 'Deliveryman assigned successfully.');
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
                    FcmService::sendOrderUpdate($order->user->fcm_token, $order->order_number ?? '#'.$order->id, $request->status, $order->id);
                } catch (\Throwable $e) {}
            }
        }

        return back()->with('success', count($ids) . ' order(s) updated to ' . $request->status . '.');
    }

}