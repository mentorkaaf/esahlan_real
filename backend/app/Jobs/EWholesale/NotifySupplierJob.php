<?php

namespace App\Jobs\EWholesale;

use App\Models\EWholesale\EWSupplier;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

/**
 * NotifySupplierJob — FCM push + Reverb broadcast to a supplier's vendor account.
 *
 * Events:
 *  new_inquiry  — buyer sent inquiry for a product
 *  new_rfq      — new public RFQ matching supplier's categories
 *  new_order    — order placed with this supplier
 *  order_cancelled — buyer/admin cancelled an order
 *  dispute_opened  — buyer opened a dispute
 */
class NotifySupplierJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int    $supplierId,
        public readonly string $event,
        public readonly array  $data = [],
    ) {}

    public function handle(FcmService $fcm): void
    {
        $supplier = EWSupplier::with('vendor')->find($this->supplierId);
        if (!$supplier || !$supplier->vendor) return;

        // ── Build message based on event ──────────────────────────────────
        [$title, $body] = match ($this->event) {
            'new_inquiry'      => [
                'New Inquiry',
                ($this->data['buyer_name'] ?? 'A buyer') . ' sent an inquiry for ' . ($this->data['product_name'] ?? 'your product'),
            ],
            'new_rfq'          => [
                'New RFQ Available',
                'New RFQ: ' . ($this->data['title'] ?? 'Bulk request') . ' — submit your quote now',
            ],
            'new_order'        => [
                'New Order Received! 🎉',
                'Order ' . ($this->data['order_no'] ?? '') . ' for ' . number_format((float)($this->data['total'] ?? 0), 2) . ' placed',
            ],
            'order_cancelled'  => [
                'Order Cancelled',
                'Order ' . ($this->data['order_no'] ?? '') . ' was cancelled',
            ],
            'dispute_opened'   => [
                'Dispute Opened',
                'Buyer opened a dispute on order ' . ($this->data['order_no'] ?? ''),
            ],
            default            => ['Notification', $this->event],
        };

        // ── FCM push to vendor's device tokens ────────────────────────────
        $vendor = $supplier->vendor;
        $tokens = collect();

        if ($vendor->fcm_token) {
            $tokens->push($vendor->fcm_token);
        }

        if ($tokens->isNotEmpty()) {
            try {
                $fcm->sendToTokens(
                    tokens:  $tokens->all(),
                    title:   $title,
                    body:    $body,
                    data:    array_merge($this->data, ['event' => $this->event]),
                );
            } catch (\Throwable $e) {
                Log::warning('EW supplier FCM failed', [
                    'supplier_id' => $this->supplierId,
                    'event'       => $this->event,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        // ── Reverb broadcast to vendor's private channel ──────────────────
        try {
            $vendorId = $vendor->user_id ?? $vendor->id;
            broadcast(new \App\Events\EWholesale\SupplierNotificationEvent(
                vendorId:  $vendorId,
                event:     $this->event,
                title:     $title,
                body:      $body,
                data:      $this->data,
            ))->toOthers();
        } catch (\Throwable $e) {
            // Non-fatal — FCM is primary
            Log::debug('EW supplier Reverb broadcast failed', ['error' => $e->getMessage()]);
        }
    }
}
