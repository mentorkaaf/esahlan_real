<?php
namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Order\OrderService;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorOrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private NotificationService $notificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $vendor = auth()->user()->vendor;
        $orders = Order::with(['user', 'items', 'deliveryman.user'])
            ->where('vendor_id', $vendor->id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->date, fn($q) => $q->whereDate('created_at', $request->date))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->paginated($orders);
    }

    public function show(Order $order): JsonResponse
    {
        $vendor = auth()->user()->vendor;
        if ($order->vendor_id !== $vendor->id) {
            return $this->error('Order not found.', 404);
        }
        $order->load(['user', 'items', 'deliveryman.user', 'statusHistory']);
        return $this->success($order);
    }

    public function accept(Order $order): JsonResponse
    {
        $vendor = auth()->user()->vendor;
        if ($order->vendor_id !== $vendor->id || $order->status !== 'pending') {
            return $this->error('Cannot accept this order.', 422);
        }

        $order->update(['status' => 'confirmed', 'confirmed_at' => now()]);
        $order->updateStatus('confirmed', 'Order confirmed by vendor', auth()->id());

        // Notify customer
        try {
            $customerToken = $order->user?->fcm_token;
            if ($customerToken) {
                $this->notificationService->sendPush(
                    [$customerToken],
                    'Order Confirmed',
                    "Your order #{$order->order_number} has been confirmed.",
                    ['order_id' => (string)$order->id, 'type' => 'order_confirmed']
                );
            }
        } catch (\Throwable $e) {}

        // Auto-dispatch
        dispatch(new \App\Jobs\AssignDeliverymanJob($order));

        return $this->success(['message' => 'Order accepted.', 'status' => 'confirmed']);
    }

    public function reject(Request $request, Order $order): JsonResponse
    {
        $vendor = auth()->user()->vendor;
        if ($order->vendor_id !== $vendor->id || !in_array($order->status, ['pending', 'confirmed'])) {
            return $this->error('Cannot reject this order.', 422);
        }

        $request->validate(['reason' => 'required|string|max:255']);
        $this->orderService->updateStatus($order, 'cancelled', $request->reason, auth()->id());

        try {
            $customerToken = $order->user?->fcm_token;
            if ($customerToken) {
                $this->notificationService->sendPush(
                    [$customerToken],
                    'Order Rejected',
                    "Your order #{$order->order_number} was rejected: {$request->reason}",
                    ['order_id' => (string)$order->id, 'type' => 'order_rejected']
                );
            }
        } catch (\Throwable $e) {}

        return $this->success(['message' => 'Order rejected.']);
    }

    public function markReady(Order $order): JsonResponse
    {
        $vendor = auth()->user()->vendor;
        if ($order->vendor_id !== $vendor->id) {
            return $this->error('Order not found.', 404);
        }

        $order->update(['status' => 'ready_for_pickup', 'ready_at' => now()]);
        $order->updateStatus('ready_for_pickup', 'Order ready for pickup', auth()->id());

        return $this->success(['message' => 'Order marked as ready for pickup.']);
    }
}
