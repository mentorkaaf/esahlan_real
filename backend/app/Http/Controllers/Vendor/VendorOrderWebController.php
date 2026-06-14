<?php
namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Notification\NotificationService;
use Illuminate\Http\Request;

class VendorOrderWebController extends Controller
{
    public function __construct(private NotificationService $notif) {}

    public function index(Request $request)
    {
        $vendor = auth()->user()->vendor;

        $orders = Order::with(['user', 'items'])
            ->where('vendor_id', $vendor->id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->date, fn($q) => $q->whereDate('created_at', $request->date))
            ->when($request->search, fn($q) => $q->where('order_number', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(20)->withQueryString();

        return view('vendor.orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $vendor = auth()->user()->vendor;
        abort_if($order->vendor_id !== $vendor->id, 404);
        $order->load(['user', 'items', 'statusHistory']);
        return view('vendor.orders.show', compact('order'));
    }

    public function accept(Order $order)
    {
        $vendor = auth()->user()->vendor;
        abort_if($order->vendor_id !== $vendor->id || $order->status !== 'pending', 422);

        $order->update(['status' => 'confirmed', 'confirmed_at' => now()]);
        $order->updateStatus('confirmed', 'Order confirmed by vendor', auth()->id());

        $this->notif->notifyUser(
            $order->user_id,
            'Order Confirmed',
            "Your order #{$order->order_number} has been confirmed.",
            ['order_id' => $order->id, 'type' => 'order_confirmed']
        );

        dispatch(new \App\Jobs\AssignDeliverymanJob($order));
        return back()->with('success', 'Order accepted successfully.');
    }

    public function reject(Request $request, Order $order)
    {
        $vendor = auth()->user()->vendor;
        abort_if($order->vendor_id !== $vendor->id, 404);
        abort_if(!in_array($order->status, ['pending', 'confirmed']), 422);

        $request->validate(['reason' => 'required|string|max:255']);

        $order->update(['status' => 'cancelled']);
        $order->updateStatus('cancelled', $request->reason, auth()->id());

        $this->notif->notifyUser(
            $order->user_id,
            'Order Rejected',
            "Your order #{$order->order_number} was rejected: {$request->reason}",
            ['order_id' => $order->id, 'type' => 'order_rejected']
        );

        return back()->with('success', 'Order rejected.');
    }

    public function markReady(Order $order)
    {
        $vendor = auth()->user()->vendor;
        abort_if($order->vendor_id !== $vendor->id, 404);

        $order->update(['status' => 'ready_for_pickup', 'ready_at' => now()]);
        $order->updateStatus('ready_for_pickup', 'Order ready for pickup', auth()->id());

        return back()->with('success', 'Order marked as ready for pickup.');
    }
}
