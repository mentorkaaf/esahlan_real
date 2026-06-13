<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function updateStatus(Order $order, string $status, ?string $note = null, ?int $actorId = null): Order
    {
        DB::transaction(function () use ($order, $status, $note, $actorId) {
            $data = ['status' => $status];

            match ($status) {
                'confirmed'        => $data['confirmed_at'] = now(),
                'ready_for_pickup' => $data['ready_at'] = now(),
                'out_for_delivery' => $data['dispatched_at'] = now(),
                'delivered'        => $data['delivered_at'] = now(),
                'cancelled'        => $data['cancelled_at'] = now(),
                default            => null,
            };

            $order->update($data);

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => $status,
                'note'       => $note ?? 'Status updated',
                'actor_id'   => $actorId,
                'actor_type' => $actorId ? 'App\\Models\\User' : null,
            ]);
        });

        return $order->fresh();
    }

    public function calculateCommission(float $subtotal, ?string $moduleSlug, ?int $vendorId = null): float
    {
        // Vendor override → module default → global setting
        if ($vendorId) {
            $vendor = \App\Models\Vendor::find($vendorId);
            if ($vendor?->commission_value) {
                return round($subtotal * $vendor->commission_value / 100, 2);
            }
        }

        if ($moduleSlug) {
            $module = \App\Models\Module::where('slug', $moduleSlug)->first();
            if ($module?->commission_value) {
                return round($subtotal * $module->commission_value / 100, 2);
            }
        }

        $globalRate = (float) settings('commission_rate', 10);
        return round($subtotal * $globalRate / 100, 2);
    }
}
