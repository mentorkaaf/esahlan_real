<?php

namespace App\Http\Controllers\Admin\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalPayment;
use App\Services\Global\StripeService;
use App\Services\Global\PayPalService;
use Illuminate\Http\Request;

class AdminGlobalRefundsController extends Controller
{
    public function index(Request $request)
    {
        $query = GlobalPayment::with('order.user');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->whereIn('status', ['paid','refunded','partial_refund']);
        }

        if ($request->filled('method')) {
            $query->where('method', $request->method);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('order', fn($q) =>
                $q->where('order_number','like',"%$s%")
                  ->orWhereHas('user', fn($u) => $u->where('email','like',"%$s%")->orWhere('name','like',"%$s%"))
            );
        }

        $payments = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $stats = [
            'total_refunded' => GlobalPayment::where('status','refunded')->sum('amount'),
            'pending_refund' => GlobalPayment::where('status','refund_pending')->count(),
            'this_month'     => GlobalPayment::where('status','refunded')->whereMonth('created_at', now()->month)->sum('amount'),
        ];

        return view('admin.global.refunds.index', compact('payments', 'stats'));
    }

    public function process(Request $request, GlobalPayment $payment)
    {
        $request->validate(['amount' => 'nullable|numeric|min:0.01']);

        if (!in_array($payment->status, ['paid'])) {
            return back()->with('error', 'This payment cannot be refunded.');
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
