<?php

namespace App\Services\EWholesale;

use App\Models\EWholesale\{
    EWProduct, EWProductVariant, EWBuyer, EWSupplier, EWSetting
};

class CartValidator
{
    public function __construct(
        private PricingService $pricing,
        private ShippingCalculator $shipping,
        private CreditService $credit,
    ) {}

    /**
     * Validate and price a wholesale cart.
     *
     * @param array $lines  [['product_id','variant_id','qty']...]
     * @param EWBuyer|null $buyer
     * @param int|null $districtId
     * @return array  {groups, errors, warnings}
     */
    public function validate(array $lines, ?EWBuyer $buyer, ?int $districtId = null): array
    {
        $errors   = [];
        $warnings = [];
        $grouped  = [];   // supplier_id → lines

        $platformFeePct = (float) EWSetting::get('platform_fee_percent', 2.5);

        foreach ($lines as $idx => $line) {
            $product = EWProduct::with(['variants','priceTiers','supplier','activeDeals'])
                ->find($line['product_id']);

            if (!$product || $product->status !== 'active') {
                $errors[] = ['line' => $idx, 'code' => 'PRODUCT_UNAVAILABLE', 'message' => "Product {$line['product_id']} is unavailable."];
                continue;
            }

            $variant = EWProductVariant::find($line['variant_id'] ?? null)
                    ?? $product->variants->where('is_default', true)->first()
                    ?? $product->variants->first();

            if (!$variant || !$variant->is_active) {
                $errors[] = ['line' => $idx, 'code' => 'VARIANT_UNAVAILABLE', 'message' => "Variant unavailable for {$product->name}."];
                continue;
            }

            $qty = (float) $line['qty'];

            // MOQ check
            if ($qty < $product->moq) {
                $errors[] = ['line' => $idx, 'code' => 'MOQ_NOT_MET', 'message' => "Min order qty for {$product->name} is {$product->moq} {$product->unit}.", 'moq' => $product->moq];
                continue;
            }

            // Stock check (warn, not error — supplier confirms)
            $available = $variant->stock_qty - $variant->reserved_qty;
            if ($variant->stock_qty > 0 && $qty > $available) {
                $warnings[] = ['line' => $idx, 'code' => 'LOW_STOCK', 'message' => "Only {$available} available for {$product->name}.", 'available' => $available];
            }

            $resolved = $this->pricing->resolve($product, $variant, $qty, $buyer);

            $grouped[$product->supplier_id][] = [
                'product_id'       => $product->id,
                'variant_id'       => $variant->id,
                'product'          => $product,
                'variant'          => $variant,
                'name'             => $product->name,
                'unit'             => $product->unit,
                'qty'              => $qty,
                'unit_price'       => $resolved['unit_price'],
                'line_total'       => round($qty * $resolved['unit_price'], 2),
                'tier_matched'     => $resolved['tier_matched'],
                'deal_applied'     => $resolved['deal_applied'],
                'price_list_applied' => $resolved['price_list_applied'],
                'savings_vs_top_tier' => $resolved['savings_vs_top_tier'],
            ];
        }

        if (!empty($errors)) {
            return ['groups' => [], 'errors' => $errors, 'warnings' => $warnings];
        }

        $availableCredit = $buyer ? $this->credit->availableCredit($buyer) : 0.0;
        $groups = [];

        foreach ($grouped as $supplierId => $suppLines) {
            $supplier  = EWSupplier::find($supplierId);
            $subtotal  = collect($suppLines)->sum('line_total');
            $delivFee  = $this->shipping->calculate($supplier, $suppLines, $subtotal, $districtId);
            // Per-supplier override > global setting
            $effectivePlatFee = $supplier->platform_fee_percent !== null
                ? (float) $supplier->platform_fee_percent
                : $platformFeePct;
            $platFee   = round($subtotal * $effectivePlatFee / 100, 2);
            $total     = round($subtotal + $delivFee + $platFee, 2);

            // Payment plans available
            $plans = ['prepaid'];
            $minDepositPct = (float) EWSetting::get('min_deposit_percent', 30);
            if ((bool) EWSetting::get('allow_partial_payment', true)) {
                $plans[] = 'deposit';
            }
            if ($buyer && $buyer->isApproved() && $availableCredit >= $total) {
                $plans[] = 'credit';
            }

            $groups[] = [
                'supplier_id'       => $supplierId,
                'supplier_name'     => $supplier->display_name,
                'supplier_verified' => $supplier->isVerified(),
                'lines'             => collect($suppLines)->map(fn($l) => collect($l)->except(['product','variant'])->toArray())->values()->toArray(),
                'subtotal'          => $subtotal,
                'delivery_fee'      => $delivFee,
                'platform_fee'      => $platFee,
                'total'             => $total,
                'available_payment_plans' => $plans,
                'min_deposit_percent'     => $minDepositPct,
            ];
        }

        return ['groups' => $groups, 'errors' => [], 'warnings' => $warnings];
    }
}
