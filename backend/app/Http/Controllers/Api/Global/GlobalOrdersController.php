<?php

namespace App\Http\Controllers\Api\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalOrder;
use Illuminate\Http\Request;

class GlobalOrdersController extends Controller
{
    public function index(Request $request)
    {
        $orders = GlobalOrder::with(['items'])
            ->where('global_user_id', $request->user('global_users')->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'orders' => $orders->through(fn($o) => $this->orderSummary($o)),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'total'        => $orders->total(),
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $order = GlobalOrder::with(['items.product', 'payments'])
            ->where('global_user_id', $request->user('global_users')->id)
            ->findOrFail($id);

        return response()->json(['order' => $this->orderDetail($order)]);
    }

    private function orderSummary(GlobalOrder $o): array
    {
        return [
            'id'           => $o->id,
            'order_number' => $o->order_number,
            'status'       => $o->status,
            'total'        => $o->total,
            'currency'     => $o->currency,
            'items_count'  => $o->items->count(),
            'thumbnail'    => $o->items->first()?->product?->thumbnail,
            'created_at'   => $o->created_at->format('M d, Y'),
        ];
    }

    private function orderDetail(GlobalOrder $o): array
    {
        return [
            'id'               => $o->id,
            'order_number'     => $o->order_number,
            'status'           => $o->status,
            'subtotal'         => $o->subtotal,
            'shipping_cost'    => $o->shipping_cost,
            'tax'              => $o->tax,
            'total'            => $o->total,
            'currency'         => $o->currency,
            'shipping_name'    => $o->shipping_name,
            'shipping_address' => implode(', ', array_filter([
                $o->shipping_address1,
                $o->shipping_city,
                $o->shipping_zip,
                $o->shipping_country,
            ])),
            'tracking_number'  => $o->tracking_number,
            'tracking_url'     => $o->tracking_url,
            'shipping_carrier' => $o->shipping_carrier,
            'notes'            => $o->notes,
            'paid_at'          => $o->paid_at?->format('M d, Y H:i'),
            'shipped_at'       => $o->shipped_at ? \Carbon\Carbon::parse($o->shipped_at)->format('M d, Y') : null,
            'created_at'       => $o->created_at->format('M d, Y H:i'),
            'items'            => $o->items->map(fn($i) => [
                'id'           => $i->id,
                'product_id'   => $i->global_product_id,
                'name'         => $i->product_name,
                'variant'      => $i->variant,
                'quantity'     => $i->quantity,
                'unit_price'   => $i->unit_price,
                'total'        => $i->total,
                'thumbnail'    => $i->product?->thumbnail,
            ]),
            'payment_method'   => $o->payments->first()?->method,
            'payment_status'   => $o->payments->first()?->status,
        ];
    }
}
