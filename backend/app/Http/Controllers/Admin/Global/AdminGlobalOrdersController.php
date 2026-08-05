<?php

namespace App\Http\Controllers\Admin\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalOrder;
use App\Models\Global\GlobalPayment;
use App\Services\Global\StripeService;
use App\Services\Global\PayPalService;
use Illuminate\Http\Request;

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

        $order->update(array_filter($data, fn($v) => $v !== null));

        return back()->with('success', 'Order updated.');
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

            return back()->with('success', 'Refund processed successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Refund failed: ' . $e->getMessage());
        }
    }
}
