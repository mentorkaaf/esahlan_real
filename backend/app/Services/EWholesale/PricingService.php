<?php

namespace App\Services\EWholesale;

use App\Models\EWholesale\{EWProduct, EWProductVariant, EWBuyer};
use Illuminate\Support\Collection;

/**
 * PricingService — authoritative B2B price resolution.
 *
 * resolve() walks the price-tier ladder for (product, variant, qty),
 * applies any buyer price-list discount, then checks active deals —
 * the BEST (lowest) unit price wins; deals and price-lists are never stacked.
 *
 * Returns:
 *  unit_price           float  — final price the buyer pays per unit
 *  tier_matched         array  — the matched tier row {min_qty,max_qty,unit_price}
 *  savings_vs_top_tier  float  — how much cheaper vs the highest (lowest-qty) tier
 *  price_list_applied   bool
 *  deal_applied         bool
 *  below_moq            bool   — true if qty < product.moq (caller should reject)
 */
class PricingService
{
    // ── Public API ────────────────────────────────────────────────────────

    /**
     * Resolve price for a single (product, variant, qty) combination.
     *
     * @param  EWProduct         $product
     * @param  EWProductVariant  $variant
     * @param  float             $qty      ordered quantity (in product's unit)
     * @param  EWBuyer|null      $buyer    null = anonymous / no price-list discount
     * @return array
     */
    public function resolve(
        EWProduct $product,
        EWProductVariant $variant,
        float $qty,
        ?EWBuyer $buyer = null,
    ): array {
        // ── 1. MOQ gate ──────────────────────────────────────────────────
        if ($qty < (float)$product->moq) {
            return $this->result(
                unitPrice: (float)($product->min_price ?? 0),
                tier: null,
                topTierPrice: (float)($product->max_price ?? 0),
                priceListApplied: false,
                dealApplied: false,
                belowMoq: true,
            );
        }

        // ── 2. Load tiers — variant-specific first, then product-level ───
        $tiers = $this->loadTiers($product, $variant);

        if ($tiers->isEmpty()) {
            // No tiers defined: fall back to min_price (shouldn't happen in prod)
            $fallback = (float)($product->min_price ?? 0);
            return $this->result($fallback, null, $fallback, false, false, false);
        }

        // ── 3. Walk ladder: find applicable tier ─────────────────────────
        $matched  = null;
        $topTier  = $tiers->first(); // highest unit_price = lowest qty tier

        foreach ($tiers as $tier) {
            $inMin = $qty >= (float)$tier->min_qty;
            $inMax = $tier->max_qty === null || $qty <= (float)$tier->max_qty;
            if ($inMin && $inMax) {
                $matched = $tier;
                // Don't break — take the LAST match (lowest price tier wins
                // when ranges can overlap, which they shouldn't in clean data)
            }
        }

        // If qty exceeds all tiers' max_qty, use the last (best-price) tier
        if ($matched === null) {
            $matched = $tiers->last();
        }

        $tierPrice    = (float)$matched->unit_price;
        $topTierPrice = (float)$topTier->unit_price;

        // ── 4. Buyer price-list discount ─────────────────────────────────
        $priceListDiscount = 0.0;
        $priceListApplied  = false;
        if ($buyer) {
            $pl = $buyer->priceList();
            if ($pl && $pl->is_active && (float)$pl->discount_percent > 0) {
                $priceListDiscount = round($tierPrice * ((float)$pl->discount_percent / 100), 4);
                $priceListApplied  = true;
            }
        }

        $priceAfterList = round($tierPrice - $priceListDiscount, 2);

        // ── 5. Active deal check ──────────────────────────────────────────
        $dealApplied = false;
        $dealPrice   = PHP_FLOAT_MAX;

        $deal = $product->activeDeals()
            ->where('min_qty', '<=', $qty)
            ->orderByDesc('deal_price_percent_off')
            ->first();

        if ($deal) {
            $dealPrice = $deal->applyTo($tierPrice); // deal always off the base tier price
        }

        // ── 6. Best price wins (deal vs price-list — never stacked) ──────
        $finalPrice = $tierPrice;

        if ($dealPrice < $priceAfterList) {
            $finalPrice  = $dealPrice;
            $dealApplied = true;
            $priceListApplied = false; // deal wins
        } elseif ($priceListApplied) {
            $finalPrice = $priceAfterList;
        }

        $finalPrice = round(max(0, $finalPrice), 2);

        return $this->result($finalPrice, $matched, $topTierPrice, $priceListApplied, $dealApplied, false);
    }

    /**
     * Resolve prices for many variants at once. Returns keyed array [variant_id => result].
     */
    public function resolveMany(EWProduct $product, Collection $variants, float $qty, ?EWBuyer $buyer = null): array
    {
        $out = [];
        foreach ($variants as $variant) {
            $out[$variant->id] = $this->resolve($product, $variant, $qty, $buyer);
        }
        return $out;
    }

    // ── Private helpers ────────────────────────────────────────────────────

    /**
     * Load price tiers: variant-specific tiers preferred over product-level tiers.
     * Returns tiers ordered by min_qty ASC (lowest quantity first = highest price).
     */
    private function loadTiers(EWProduct $product, EWProductVariant $variant): Collection
    {
        // Variant-level tiers (if any defined for this specific variant)
        $variantTiers = $product->priceTiers()
            ->where('variant_id', $variant->id)
            ->orderBy('min_qty')
            ->get();

        if ($variantTiers->isNotEmpty()) {
            return $variantTiers;
        }

        // Product-level tiers (variant_id IS NULL = applies to all variants)
        return $product->priceTiers()
            ->whereNull('variant_id')
            ->orderBy('min_qty')
            ->get();
    }

    private function result(
        float $unitPrice,
        mixed $tier,
        float $topTierPrice,
        bool $priceListApplied,
        bool $dealApplied,
        bool $belowMoq,
    ): array {
        return [
            'unit_price'          => $unitPrice,
            'tier_matched'        => $tier ? [
                'min_qty'    => (float)$tier->min_qty,
                'max_qty'    => $tier->max_qty !== null ? (float)$tier->max_qty : null,
                'unit_price' => (float)$tier->unit_price,
            ] : null,
            'savings_vs_top_tier' => round(max(0, $topTierPrice - $unitPrice), 2),
            'price_list_applied'  => $priceListApplied,
            'deal_applied'        => $dealApplied,
            'below_moq'           => $belowMoq,
        ];
    }
}
