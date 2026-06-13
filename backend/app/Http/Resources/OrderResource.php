<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'order_number'         => $this->order_number,
            'status'               => $this->status,
            'payment_method'       => $this->payment_method,
            'payment_status'       => $this->payment_status,
            'subtotal'             => $this->subtotal,
            'delivery_fee'         => $this->delivery_fee,
            'tax_amount'           => $this->tax_amount,
            'discount_amount'      => $this->discount_amount,
            'total'                => $this->total,
            'currency'             => 'USD',
            'delivery_address'     => $this->delivery_address,
            'delivery_latitude'    => $this->delivery_latitude,
            'delivery_longitude'   => $this->delivery_longitude,
            'notes'                => $this->notes,
            'placed_at'            => $this->placed_at?->toISOString(),
            'confirmed_at'         => $this->confirmed_at?->toISOString(),
            'delivered_at'         => $this->delivered_at?->toISOString(),
            'cancelled_at'         => $this->cancelled_at?->toISOString(),
            'vendor'               => $this->whenLoaded('vendor', fn() => [
                'id'     => $this->vendor->id,
                'name'   => $this->vendor->name,
                'logo'   => $this->vendor->logo_url,
                'phone'  => $this->vendor->phone,
            ]),
            'deliveryman'          => $this->whenLoaded('deliveryman', fn() => $this->deliveryman ? [
                'id'     => $this->deliveryman->id,
                'name'   => $this->deliveryman->user?->name,
                'phone'  => $this->deliveryman->user?->phone,
                'avatar' => $this->deliveryman->user?->avatar_url,
                'rating' => $this->deliveryman->rating,
            ] : null),
            'items'                => $this->whenLoaded('items'),
            'status_history'       => $this->whenLoaded('statusHistory'),
            'tracking'             => $this->whenLoaded('tracking', fn() => $this->tracking->last()),
            'created_at'           => $this->created_at?->toISOString(),
        ];
    }
}
