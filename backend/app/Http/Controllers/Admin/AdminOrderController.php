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
    public function index(Request $request)
    {
        $query = Order::with(['user', 'vendor', 'deliveryman'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->module, fn($q) => $q->where('module_slug', $request->module))
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->when($request->search, fn($q) => $q->where('order_number', 'like', "%{$request->search}%"))
            ->latest();

        $orders = $query->paginate(20);

        $statusCounts = Order::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $exchangeOrders = DB::table('exchange_orders')
            ->join('users', 'users.id', '=', 'exchange_orders.user_id')
            ->select('exchange_orders.*', 'users.name as user_name', 'users.phone as user_phone')
            ->orderByDesc('exchange_orders.created_at')
            ->limit(50)
            ->get();

        return view('admin.orders.index', compact('orders', 'statusCounts', 'exchangeOrders'));
    }

    public function show(Order $order)
    {
        try {
            $order->load(['user', 'vendor', 'deliveryman', 'items.product', 'statusHistory']);
        } catch (\Throwable $e) {
            // Load without statusHistory if table issues
            $order->load(['user', 'vendor', 'deliveryman', 'items.product']);
        }
        $deliverymen = Deliveryman::where('is_approved', true)->where('status', 'available')->with('user')->get();
        return view('admin.orders.show', compact('order', 'deliverymen'));
    }

    public function updateStatus(Request $request, Order $order)
    {
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