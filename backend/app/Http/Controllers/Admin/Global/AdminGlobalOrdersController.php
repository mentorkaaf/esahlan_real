<?php

namespace App\Http\Controllers\Admin\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalOrder;
use App\Models\Global\GlobalPayment;
use App\Services\AdminAlertService;
use App\Services\Global\StripeService;
use App\Services\Global\PayPalService;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdminGlobalOrdersController extends Controller
{
    public function index(Request $request)
    {
        $query = GlobalOrder::with('user', 'items');

        if ($request->filled('status'))  { $query->where('status', $request->status); }
        if ($request->filled('search'))  {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('order_number', 'like', "%$s%")
                  ->orWhereHas('user', fn($u) => $u->where('name','like',"%$s%")->orWhere('email','like',"%$s%"));
            });
        }
        if ($request->filled('payment')) { $query->where('payment_status', $request->payment); }
        if ($request->filled('from'))    { $query->where('created_at', '>=', $request->from); }
        if ($request->filled('to'))      { $query->where('created_at', '<=', $request->to . ' 23:59:59'); }

        $orders = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        return view('admin.global.orders.index', compact('orders'));
    }

    public function show(GlobalOrder $order)
    {
        $order->load('user', 'items.product', 'payment');
        return view('admin.global.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, GlobalOrder $order)
    {
        $request->validate([
            'status'              => 'nullable|in:pending,paid,processing,shipped,delivered,cancelled,refunded,on_hold',
            'fulfillment_status'  => 'nullable|in:unfulfilled,partial,fulfilled,shipped,delivered',
            'tracking_number'     => 'nullable|string',
            'shipping_carrier'    => 'nullable|string',
            'tracking_url'        => 'nullable|url',
            'admin_notes'         => 'nullable|string',
        ]);

        $data = $request->only([
            'status','fulfillment_status','tracking_number',
            'shipping_carrier','tracking_url','admin_notes',
        ]);

        if ($request->status === 'shipped' && !$order->shipped_at) {
            $data['shipped_at'] = now();
        }
        if ($request->status === 'delivered' && !$order->delivered_at) {
            $data['delivered_at'] = now();
            $data['fulfillment_status'] = 'delivered';
        }

        $oldStatus = $order->status;
        $order->update(array_filter($data, fn($v) => $v !== null));

        // Send FCM push to customer if status changed
        $newStatus = $request->status;
        if ($newStatus && $newStatus !== $oldStatus) {
            $this->notifyCustomer($order->fresh(), $newStatus);
            // Send review request notification immediately on delivery
            if ($newStatus === 'delivered') {
                $this->sendDeliveryReviewRequest($order->fresh());
            }
            // Admin alert for cancelled global orders
            if ($newStatus === 'cancelled') {
                try {
                    $fresh = $order->fresh();
                    AdminAlertService::send('order_cancelled', "❌ Global Order Cancelled: {$fresh->order_number}", [
                        'Order #'      => $fresh->order_number,
                        'Customer'     => trim($fresh->ship_first_name . ' ' . $fresh->ship_last_name),
                        'Total'        => '$' . number_format($fresh->total, 2),
                        'Module'       => 'GLOBAL STORE',
                        'Cancelled At' => now()->format('d M Y H:i') . ' UTC',
                    ], 'global_cancelled_' . $fresh->id, 300);
                } catch (\Throwable) {}
            }
        }

        return back()->with('success', 'Order updated.');
    }

    private function notifyCustomer(GlobalOrder $order, string $status): void
    {
        try {
            $fcmToken = $order->user?->fcm_token ?? null;
            if (empty($fcmToken)) return;

            $messages = [
                'processing' => ['🔄 Order Processing', 'Your order ' . $order->order_number . ' is being processed.'],
                'shipped'    => ['🚚 Order Shipped!', 'Your order ' . $order->order_number . ' is on its way!'],
                'delivered'  => ['📦 Order Delivered!', 'Your order ' . $order->order_number . ' has been delivered.'],
                'cancelled'  => ['❌ Order Cancelled', 'Your order ' . $order->order_number . ' has been cancelled.'],
                'refunded'   => ['💰 Order Refunded', 'Your order ' . $order->order_number . ' has been refunded.'],
                'on_hold'    => ['⏸ Order On Hold', 'Your order ' . $order->order_number . ' is on hold. We\'ll contact you soon.'],
            ];

            [$title, $body] = $messages[$status] ?? ['🛍 Order Update', 'Your order ' . $order->order_number . ' status: ' . $status];

            FcmService::sendToToken($fcmToken, $title, $body, [
                'type'         => 'global_order_update',
                'order_id'     => (string) $order->id,
                'order_number' => $order->order_number,
                'status'       => $status,
                'deep_link'    => '/global/orders',
            ]);
        } catch (\Throwable $e) {
            Log::error('[GlobalOrders] FCM notify failed', ['error' => $e->getMessage()]);
        }
    }

    private function sendDeliveryReviewRequest(GlobalOrder $order): void
    {
        try {
            $user = $order->user;
            if (!$user) return;

            $firstItem = $order->items->first();
            $productName = $firstItem?->product_name ?? 'your recent purchase';

            // FCM
            if ($user->fcm_token) {
                FcmService::sendToToken(
                    $user->fcm_token,
                    '📦 Order Delivered! ⭐ Leave a Review',
                    "Your order {$order->order_number} arrived! Tell us what you think of $productName",
                    ['type' => 'review_request', 'order_id' => (string) $order->id, 'deep_link' => '/global/orders']
                );
            }

            // Email
            Mail::to($user->email)->queue(new \App\Mail\Global\GlobalReviewRequestMail([
                'name'         => $user->name,
                'order_number' => $order->order_number,
                'product_name' => $productName,
                'order_id'     => $order->id,
                'is_reminder'  => false,
            ]));

            // Mark as notified
            DB::table('global_orders')->where('id', $order->id)->update([
                'review_notified_at'    => now(),
                'review_reminder_count' => 1,
            ]);

        } catch (\Throwable $e) {
            Log::error('[GlobalOrders] Review request failed: ' . $e->getMessage());
        }
    }

    public function refund(Request $request, GlobalOrder $order)
    {
        $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
        ]);

        $payment = $order->payment;
        if (!$payment || $payment->status === 'refunded') {
            return back()->with('error', 'No eligible payment found.');
        }

        try {
            $amount = $request->filled('amount') ? (float)$request->amount : null;

            if ($payment->method === 'stripe') {
                (new StripeService())->refund($payment, $amount);
            } else {
                (new PayPalService())->refund($payment, $amount);
            }

            // Admin alert — global store refund issued
            try {
                $refundAmt = $amount ?? (float) $order->total;
                AdminAlertService::send('global_order_refunded', "💸 Global Refund: {$order->order_number}", [
                    'Order #'     => $order->order_number,
                    'Customer'    => trim($order->ship_first_name . ' ' . $order->ship_last_name),
                    'Refund Amt'  => '$' . number_format($refundAmt, 2),
                    'Order Total' => '$' . number_format($order->total, 2),
                    'Gateway'     => strtoupper($payment->method ?? 'N/A'),
                    'Refunded At' => now()->format('d M Y H:i') . ' UTC',
                ], 'global_refund_' . $order->id, 300);
            } catch (\Throwable) {}

            return back()->with('success', 'Refund processed successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Refund failed: ' . $e->getMessage());
        }
    }

    /** DELETE /admin/global/orders/{order} — single delete */
    public function destroy(GlobalOrder $order)
    {
        $order->items()->delete();
        $order->delete();
        return redirect()->route('admin.global.orders.index')->with('success', 'Order deleted.');
    }

    /** POST /admin/global/orders/bulk — bulk delete or bulk status change */
    public function bulk(Request $request)
    {
        $request->validate([
            'action'  => 'required|in:delete,status',
            'ids'     => 'required|array|min:1',
            'ids.*'   => 'integer',
            'status'  => 'required_if:action,status|nullable|in:pending,processing,shipped,delivered,cancelled,refunded,on_hold',
        ]);

        $ids = $request->ids;

        if ($request->action === 'delete') {
            DB::table('global_order_items')->whereIn('global_order_id', $ids)->delete();
            GlobalOrder::whereIn('id', $ids)->delete();
            $count = count($ids);
            return back()->with('success', "$count order(s) deleted.");
        }

        if ($request->action === 'status') {
            $newStatus = $request->status;
            $data = ['status' => $newStatus];
            if ($newStatus === 'shipped')   $data['shipped_at']   = now();
            if ($newStatus === 'delivered') { $data['delivered_at'] = now(); $data['fulfillment_status'] = 'delivered'; }

            $orders = GlobalOrder::with('user')->whereIn('id', $ids)->get();
            foreach ($orders as $order) {
                $old = $order->status;
                $order->update($data);
                if ($newStatus !== $old) {
                    $this->notifyCustomer($order->fresh(), $newStatus);
                }
            }
            $count = count($ids);
            return back()->with('success', "$count order(s) updated to \"$newStatus\".");
        }

        return back()->with('error', 'Unknown action.');
    }
}
