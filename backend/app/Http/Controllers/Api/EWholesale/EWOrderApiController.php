<?php

namespace App\Http\Controllers\Api\EWholesale;

use App\Http\Controllers\Controller;
use App\Models\EWholesale\{
    EWBuyer, EWProduct, EWProductVariant, EWSupplier,
    EWOrder, EWOrderItem, EWOrderPayment, EWDispute, EWActivityLog, EWSetting
};
use App\Events\EWholesale\{EWBuyerEvent, EWSupplierEvent};
use App\Services\EWholesale\{PricingService, CreditService, ShippingCalculator, CartValidator};
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EWOrderApiController extends Controller
{
    public function __construct(
        private PricingService   $pricing,
        private CreditService    $credit,
        private ShippingCalculator $shipping,
        private CartValidator    $cartValidator,
    ) {}

    // ── POST /api/v1/ewholesale/orders ────────────────────────────────────
    //
    // groups: [
    //   { supplier_id, lines:[{product_id,variant_id,qty}],
    //     fulfillment, address_id?, payment_plan, deposit_percent?,
    //     quote_id? }
    // ]
    public function checkout(Request $r)
    {
        $r->validate([
            'groups'                          => 'required|array|min:1|max:10',
            'groups.*.supplier_id'            => 'required|integer',
            'groups.*.lines'                  => 'required|array|min:1',
            'groups.*.lines.*.product_id'     => 'required|integer',
            'groups.*.lines.*.variant_id'     => 'nullable|integer',
            'groups.*.lines.*.qty'            => 'required|numeric|min:0.01',
            'groups.*.fulfillment'            => 'required|in:delivery,pickup',
            'groups.*.address_id'             => 'nullable|integer',
            'groups.*.payment_plan'           => 'required|in:prepaid,deposit,credit',
            'groups.*.deposit_percent'        => 'nullable|numeric|min:10|max:100',
            'groups.*.quote_id'               => 'nullable|integer',
        ]);

        $user  = $r->user();
        $buyer = EWBuyer::with(['creditAccount','priceListLink.priceList'])
                        ->where('user_id', $user->id)->first();

        if (!$buyer) {
            return $this->error('KYB_REQUIRED', 'Register as a wholesale buyer first.', 403);
        }
        if (!$buyer->isApproved()) {
            return $this->error('KYB_REQUIRED', 'Your KYB is not yet approved. You cannot place orders.', 403);
        }

        $platformFeePct = (float) EWSetting::get('platform_fee_percent', 2.5);
        $minDepPct      = (float) EWSetting::get('min_deposit_percent', 30);
        $createdOrders  = [];

        DB::beginTransaction();

        try {
            foreach ($r->groups as $group) {
                $supplier = EWSupplier::findOrFail($group['supplier_id']);

                // ── 1. Re-price all lines inside transaction ──────────────
                $resolvedLines = [];
                $subtotal      = 0;

                foreach ($group['lines'] as $line) {
                    $product = EWProduct::with(['variants','priceTiers','activeDeals'])->lockForUpdate()->find($line['product_id']);
                    if (!$product || $product->status !== 'active') {
                        throw $this->checkoutException('PRODUCT_UNAVAILABLE', "Product {$line['product_id']} is unavailable.");
                    }

                    $variant = EWProductVariant::lockForUpdate()
                        ->find($line['variant_id'] ?? null)
                        ?? $product->variants->where('is_default', true)->first()
                        ?? $product->variants->first();

                    if (!$variant) {
                        throw $this->checkoutException('VARIANT_UNAVAILABLE', "No variant for {$product->name}.");
                    }

                    $qty = (float) $line['qty'];
                    if ($qty < $product->moq) {
                        throw $this->checkoutException('MOQ_NOT_MET', "Min order for {$product->name} is {$product->moq} {$product->unit}.", ['moq'=>$product->moq]);
                    }

                    // Stock check
                    if ($product->stock_qty > 0) {
                        $available = $variant->stock_qty - $variant->reserved_qty;
                        if ($qty > $available) {
                            throw $this->checkoutException('STOCK_CHANGED', "Only {$available} available for {$product->name}.", ['available'=>$available]);
                        }
                    }

                    $resolved  = $this->pricing->resolve($product, $variant, $qty, $buyer);
                    $lineTotal = round($qty * $resolved['unit_price'], 2);
                    $subtotal += $lineTotal;

                    $resolvedLines[] = compact('product','variant','qty','resolved','lineTotal');
                }

                // Delivery + platform fee
                $deliveryFee = $this->shipping->calculate($supplier, array_map(fn($l) => [
                    'product'    => $l['product'],
                    'variant'    => $l['variant'],
                    'qty'        => $l['qty'],
                    'unit_price' => $l['resolved']['unit_price'],
                ], $resolvedLines), $subtotal, null);

                $platformFee = round($subtotal * $platformFeePct / 100, 2);
                $total       = round($subtotal + $deliveryFee + $platformFee, 2);

                // ── 2. Payment plan checks ────────────────────────────────
                $payPlan      = $group['payment_plan'];
                $depositPct   = null;

                if ($payPlan === 'credit') {
                    if (!$buyer->creditAccount || !$buyer->creditAccount->isActive()) {
                        throw $this->checkoutException('CREDIT_EXCEEDED', 'No active credit account.');
                    }
                    $avail = $this->credit->availableCredit($buyer);
                    if ($avail < $total) {
                        throw $this->checkoutException('CREDIT_EXCEEDED', "Insufficient credit. Available: \${$avail}, needed: \${$total}.");
                    }
                }

                if ($payPlan === 'deposit') {
                    $depositPct = max($minDepPct, (float) ($group['deposit_percent'] ?? $minDepPct));
                }

                // ── 3. Create order ──────────────────────────────────────
                $order = EWOrder::create([
                    'order_no'         => EWOrder::generateOrderNo(),
                    'buyer_id'         => $buyer->id,
                    'supplier_id'      => $supplier->id,
                    'source'           => isset($group['quote_id']) ? 'quote' : 'cart',
                    'quote_id'         => $group['quote_id'] ?? null,
                    'status'           => 'pending_confirmation',
                    'payment_plan'     => $payPlan,
                    'deposit_percent'  => $depositPct,
                    'subtotal'         => $subtotal,
                    'delivery_fee'     => $deliveryFee,
                    'platform_fee'     => $platformFee,
                    'total'            => $total,
                    'paid_total'       => 0,
                    'fulfillment'      => $group['fulfillment'],
                    'address_id'       => $group['address_id'] ?? null,
                ]);

                // ── 4. Create items + reserve stock ───────────────────────
                foreach ($resolvedLines as $l) {
                    EWOrderItem::create([
                        'order_id'            => $order->id,
                        'product_id'          => $l['product']->id,
                        'variant_id'          => $l['variant']->id,
                        'name_snapshot'       => $l['product']->name,
                        'unit_snapshot'       => $l['product']->unit,
                        'qty'                 => $l['qty'],
                        'unit_price_snapshot' => $l['resolved']['unit_price'],
                        'line_total'          => $l['lineTotal'],
                    ]);

                    // Reserve stock
                    EWProductVariant::where('id', $l['variant']->id)
                        ->increment('reserved_qty', $l['qty']);
                }

                // ── 5. Handle payment plan ────────────────────────────────
                if ($payPlan === 'credit') {
                    $this->credit->charge($buyer->creditAccount, $total, $order->id, 'Order '.$order->order_no);
                    $order->update(['paid_total' => $total, 'status' => 'confirmed']);
                    EWOrderPayment::create([
                        'order_id' => $order->id,
                        'type'     => 'full',
                        'method'   => 'credit',
                        'amount'   => $total,
                        'status'   => 'confirmed',
                        'paid_at'  => now(),
                    ]);
                } elseif ($payPlan === 'deposit') {
                    // Deposit amount charged separately via pay-balance endpoint
                    $order->update(['status' => 'awaiting_payment']);
                } else {
                    // prepaid — order awaits payment
                    $order->update(['status' => 'awaiting_payment']);
                }

                // ── 6. Audit + Realtime ───────────────────────────────────
                EWActivityLog::record('order.placed', $order, [], ['total'=>$total,'plan'=>$payPlan], 'user', $user->id);

                broadcast(new EWSupplierEvent(
                    $supplier->vendor_id,
                    'new_order',
                    ['order_no'=>$order->order_no,'total'=>$total,'buyer_name'=>$user->name]
                ))->toOthers();

                // FCM push to supplier
                \App\Jobs\EWholesale\NotifySupplierJob::dispatch(
                    $supplier->id, 'new_order',
                    ['order_no'=>$order->order_no,'total'=>$total,'buyer_name'=>$user->name]
                );

                $createdOrders[] = ['order_id' => $order->id, 'order_no' => $order->order_no, 'total' => $total, 'status' => $order->fresh()->status];
            }

            DB::commit();

        } catch (\RuntimeException $e) {
            DB::rollBack();
            $data = json_decode($e->getMessage(), true);
            return response()->json(['error' => $data], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'message' => count($createdOrders).' order(s) placed successfully.',
            'data'    => ['orders' => $createdOrders],
        ], 201);
    }

    // ── GET /api/v1/ewholesale/orders ────────────────────────────────────
    public function index(Request $r)
    {
        $buyer  = $this->buyer($r);
        $q      = EWOrder::with('supplier:id,display_name,logo')
                         ->where('buyer_id', $buyer->id);
        if ($r->status) $q->where('status', $r->status);
        $orders = $q->orderByDesc('id')->paginate(20);

        return response()->json([
            'data' => $orders->getCollection()->map(fn($o) => $this->orderSummary($o)),
            'meta' => ['current_page'=>$orders->currentPage(),'last_page'=>$orders->lastPage(),'total'=>$orders->total()],
        ]);
    }

    // ── GET /api/v1/ewholesale/orders/{id} ───────────────────────────────
    public function show(Request $r, int $id)
    {
        $buyer = $this->buyer($r);
        $order = EWOrder::with(['supplier','items.product','items.variant','payments','shipments','disputes'])->findOrFail($id);
        if ($order->buyer_id !== $buyer->id) abort(403);

        $depositDue = null;
        $balanceDue = null;
        if ($order->payment_plan === 'deposit' && $order->deposit_percent) {
            $depositDue = round($order->total * $order->deposit_percent / 100, 2);
            $balanceDue = round($order->total - $depositDue, 2);
        }

        return response()->json([
            'data' => array_merge($this->orderSummary($order), [
                'items'      => $order->items->map(fn($i) => [
                    'id'           => $i->id,
                    'product_id'   => $i->product_id,
                    'name'         => $i->name_snapshot,
                    'unit'         => $i->unit_snapshot,
                    'qty'          => $i->qty,
                    'unit_price'   => $i->unit_price_snapshot,
                    'line_total'   => $i->line_total,
                    'shipped_qty'  => $i->shipped_qty,
                ]),
                'payments'   => $order->payments->map(fn($p) => [
                    'type'   => $p->type,
                    'method' => $p->method,
                    'amount' => $p->amount,
                    'paid_at'=> $p->paid_at?->toIso8601String(),
                ]),
                'shipments'  => $order->shipments->map(fn($s) => [
                    'shipment_no'  => $s->shipment_no,
                    'status'       => $s->status,
                    'items'        => $s->items,
                    'dispatched_at'=> $s->dispatched_at?->toIso8601String(),
                    'delivered_at' => $s->delivered_at?->toIso8601String(),
                ]),
                'disputes'   => $order->disputes->map(fn($d) => [
                    'id'     => $d->id,
                    'reason' => $d->reason,
                    'status' => $d->status,
                ]),
                'payment_plan_progress' => [
                    'plan'        => $order->payment_plan,
                    'total'       => $order->total,
                    'paid'        => $order->paid_total,
                    'balance_due' => $order->balanceDue(),
                    'deposit_due' => $depositDue,
                ],
                'tracking_channel' => in_array($order->status, ['shipped','partially_shipped'])
                    ? "private-ewholesale.order.{$order->id}" : null,
            ]),
        ]);
    }

    // ── POST /api/v1/ewholesale/orders/{id}/pay-balance ──────────────────
    public function payBalance(Request $r, int $id)
    {
        $r->validate(['method' => 'required|in:wallet,evc,cash,bank,credit']);

        $buyer = $this->buyer($r);
        $order = EWOrder::findOrFail($id);
        if ($order->buyer_id !== $buyer->id) abort(403);

        $balanceDue = $order->balanceDue();
        if ($balanceDue <= 0) {
            return response()->json(['message' => 'Order is fully paid.'], 422);
        }

        DB::transaction(function () use ($order, $buyer, $balanceDue, $r) {
            if ($r->method === 'credit') {
                $this->credit->charge($buyer->creditAccount, $balanceDue, $order->id, 'Balance payment '.$order->order_no);
            }
            EWOrderPayment::create([
                'order_id' => $order->id,
                'type'     => 'balance',
                'method'   => $r->method,
                'amount'   => $balanceDue,
                'status'   => 'confirmed',
                'paid_at'  => now(),
            ]);
            $order->increment('paid_total', $balanceDue);
            if ($order->fresh()->balanceDue() <= 0) {
                $order->update(['status' => 'processing']);
            }
        });

        return response()->json(['message' => 'Balance payment recorded.', 'data' => ['paid' => $balanceDue]]);
    }

    // ── POST /api/v1/ewholesale/orders/{id}/cancel ───────────────────────
    public function cancel(Request $r, int $id)
    {
        $buyer = $this->buyer($r);
        $order = EWOrder::with('items.variant')->findOrFail($id);
        if ($order->buyer_id !== $buyer->id) abort(403);

        if (!in_array($order->status, ['pending_confirmation','awaiting_payment'])) {
            return response()->json(['message' => 'Order cannot be cancelled at this stage.'], 422);
        }

        DB::transaction(function () use ($order, $buyer, $r) {
            $order->update(['status' => 'cancelled', 'cancelled_reason' => $r->reason ?? 'Buyer cancelled']);

            // Release stock reservations
            foreach ($order->items as $item) {
                EWProductVariant::where('id', $item->variant_id)
                    ->decrement('reserved_qty', $item->qty);
            }

            // Release credit if used
            if ($order->payment_plan === 'credit') {
                $this->credit->releaseForOrder($order->id);
            }
        });

        EWActivityLog::record('order.cancelled', $order, [], ['reason'=>$r->reason ?? 'buyer'], 'user', $r->user()->id);
        return response()->json(['message' => 'Order cancelled.']);
    }

    // ── POST /api/v1/ewholesale/orders/{id}/dispute ───────────────────────
    public function dispute(Request $r, int $id)
    {
        $r->validate([
            'reason'      => 'required|string|max:200',
            'description' => 'required|string|max:2000',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $buyer = $this->buyer($r);
        $order = EWOrder::findOrFail($id);
        if ($order->buyer_id !== $buyer->id) abort(403);

        if (!in_array($order->status, ['delivered','shipped','partially_shipped'])) {
            return response()->json(['message' => 'Cannot open dispute for order in status: '.$order->status.'.'], 422);
        }

        if ($order->disputes()->where('status','!=','closed')->exists()) {
            return response()->json(['message' => 'An open dispute already exists.'], 422);
        }

        $paths = [];
        foreach ($r->file('attachments', []) as $file) {
            $paths[] = $file->store('ewholesale/disputes', 'public');
        }

        $slaDays = (int) \App\Models\EWholesale\EWSetting::get('dispute_sla_hours', 72);
        EWDispute::create([
            'order_id'    => $order->id,
            'opened_by'   => $r->user()->id,
            'reason'      => $r->reason,
            'description' => $r->description,
            'attachments' => $paths ?: null,
            'sla_deadline'=> now()->addHours($slaDays),
        ]);

        // Notify supplier
        \App\Jobs\EWholesale\NotifySupplierJob::dispatch(
            $order->supplier_id, 'dispute_opened',
            ['order_no' => $order->order_no]
        );

        $order->update(['status' => 'disputed']);
        return response()->json(['message' => 'Dispute submitted. Our team will review within 24 hours.'], 201);
    }

    // ── POST /api/v1/ewholesale/orders/{id}/review ────────────────────────
    public function review(Request $r, int $id)
    {
        $r->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $buyer = $this->buyer($r);
        $order = EWOrder::findOrFail($id);
        if ($order->buyer_id !== $buyer->id) abort(403);
        if ($order->status !== 'completed') {
            return response()->json(['message' => 'Can only review completed orders.'], 422);
        }

        \App\Models\EWholesale\EWReview::updateOrCreate(
            ['order_id' => $order->id, 'buyer_id' => $buyer->id],
            ['supplier_id' => $order->supplier_id, 'rating' => $r->rating, 'comment' => $r->comment]
        );

        // Update supplier average rating
        $avg = \App\Models\EWholesale\EWReview::where('supplier_id', $order->supplier_id)->avg('rating');
        $order->supplier->update(['rating' => round($avg, 2)]);

        return response()->json(['message' => 'Review submitted. Thank you!']);
    }

    // ── POST /api/v1/ewholesale/orders/{id}/reorder ──────────────────────
    public function reorder(Request $r, int $id)
    {
        $buyer = $this->buyer($r);
        $order = EWOrder::with('items.product')->findOrFail($id);
        if ($order->buyer_id !== $buyer->id) abort(403);

        $lines   = [];
        $changes = [];

        foreach ($order->items as $item) {
            $product = $item->product;
            $variant = EWProductVariant::find($item->variant_id);

            if (!$product || $product->status !== 'active') {
                $changes[] = "Product '{$item->name_snapshot}' is no longer available.";
                continue;
            }

            $resolved  = $this->pricing->resolve($product, $variant, $item->qty, $buyer);
            $newPrice  = $resolved['unit_price'];
            $oldPrice  = $item->unit_price_snapshot;

            if (abs($newPrice - $oldPrice) > 0.01) {
                $changes[] = "{$item->name_snapshot}: price changed from \${$oldPrice} to \${$newPrice}.";
            }

            $lines[] = [
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'qty'        => $item->qty,
                'unit_price' => $newPrice,
            ];
        }

        // Run through cart validator for full group breakdown
        $validated = $this->cartValidator->validate(
            $lines,
            $buyer,
        );

        return response()->json([
            'data' => [
                'groups'  => $validated['groups'],
                'changes' => $changes,
                'errors'  => $validated['errors'],
                'warnings'=> $validated['warnings'],
            ],
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────
    private function buyer(Request $r): EWBuyer
    {
        $b = EWBuyer::with('creditAccount')->where('user_id', $r->user()->id)->first();
        abort_unless($b, 404, 'Buyer profile not found.');
        return $b;
    }

    private function orderSummary(EWOrder $o): array
    {
        return [
            'id'           => $o->id,
            'order_no'     => $o->order_no,
            'supplier_name'=> $o->supplier?->display_name,
            'status'       => $o->status,
            'payment_plan' => $o->payment_plan,
            'total'        => $o->total,
            'paid_total'   => $o->paid_total,
            'balance_due'  => $o->balanceDue(),
            'created_at'   => $o->created_at->toIso8601String(),
            'confirmed_at' => $o->confirmed_at?->toIso8601String(),
            'completed_at' => $o->completed_at?->toIso8601String(),
        ];
    }

    private function checkoutException(string $code, string $message, array $extra = []): \RuntimeException
    {
        return new \RuntimeException(json_encode(array_merge(['code' => $code, 'message' => $message], $extra)));
    }

    private function error(string $code, string $message, int $status): \Illuminate\Http\JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
