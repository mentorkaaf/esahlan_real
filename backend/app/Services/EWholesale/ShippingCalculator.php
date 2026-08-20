<?php

namespace App\Services\EWholesale;

use App\Models\EWholesale\{EWSupplier, EWShippingRule};

class ShippingCalculator
{
    /**
     * Calculate delivery fee for a group of cart lines belonging to one supplier.
     *
     * @param EWSupplier $supplier
     * @param array $lines  [['product'=>EWProduct,'variant'=>EWProductVariant,'qty'=>float,'unit_price'=>float]]
     * @param float $subtotal
     * @param int|null $districtId
     * @return float
     */
    public function calculate(EWSupplier $supplier, array $lines, float $subtotal, ?int $districtId = null): float
    {
        // Supplier-specific rule first, then platform default
        $rule = EWShippingRule::where('supplier_id', $supplier->id)
                              ->where('is_active', true)
                              ->when($districtId, fn($q) => $q->where(fn($q) =>
                                  $q->whereNull('zone_district_ids')
                                    ->orWhereJsonContains('zone_district_ids', $districtId)
                              ))
                              ->first()
               ?? EWShippingRule::whereNull('supplier_id')->where('is_active', true)->first();

        if (!$rule) {
            return 0.0;
        }

        // Free shipping threshold
        if ($rule->free_over && $subtotal >= $rule->free_over) {
            return 0.0;
        }

        return match ($rule->basis) {
            'flat_by_zone' => (float) $rule->rate,
            'per_carton'   => $this->totalCartons($lines) * $rule->rate,
            'per_kg'       => $this->totalWeight($lines) * $rule->rate,
            'per_cbm'      => $this->totalVolume($lines) * $rule->rate,
            default        => (float) $rule->rate,
        };
    }

    private function totalCartons(array $lines): float
    {
        return collect($lines)->sum('qty');
    }

    private function totalWeight(array $lines): float
    {
        return collect($lines)->sum(fn($l) => ($l['variant']?->weight_kg ?? 0) * $l['qty']);
    }

    private function totalVolume(array $lines): float
    {
        return collect($lines)->sum(fn($l) => ($l['variant']?->volume_cbm ?? 0) * $l['qty']);
    }
}
