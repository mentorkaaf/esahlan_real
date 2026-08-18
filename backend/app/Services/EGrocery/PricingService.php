<?php
namespace App\Services\EGrocery;

use App\Models\EGrocery\{EGroceryProductVariant, EGroceryFlashDeal};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PricingService
{
    /**
     * Resolve effective price for a single variant.
     * Checks active flash deals (section must be active + qty not exhausted).
     * Returns:
     *   effective_price  – what the customer pays
     *   original_price   – compare_price if set, else price (for strike-through)
     *   discount_pct     – 0–100 integer, 0 means no discount
     *   is_flash_deal    – bool
     *   flash_deal_id    – int|null
     */
    public function resolve(EGroceryProductVariant $variant): array
    {
        // Check flash deal (cached per variant, 60s)
        $flashDeal = Cache::remember(
            "egrocery:flash:{$variant->id}",
            60,
            fn() => EGroceryFlashDeal::with('section')
                ->where('variant_id', $variant->id)
                ->whereHas('section', fn($q) => $q->active())
                ->where(function ($q) {
                    $q->whereNull('qty_limit')
                      ->orWhereColumn('qty_sold', '<', 'qty_limit');
                })
                ->first()
        );

        if ($flashDeal) {
            $effectivePrice = (float) $flashDeal->deal_price;
            $originalPrice  = (float) $variant->price;
            $discountPct    = $originalPrice > 0
                ? (int) round((($originalPrice - $effectivePrice) / $originalPrice) * 100)
                : 0;
            return [
                'effective_price' => number_format($effectivePrice, 2, '.', ''),
                'original_price'  => number_format($originalPrice, 2, '.', ''),
                'discount_pct'    => $discountPct,
                'is_flash_deal'   => true,
                'flash_deal_id'   => $flashDeal->id,
            ];
        }

        $price        = (float) $variant->price;
        $comparePrice = $variant->compare_price ? (float) $variant->compare_price : null;
        $discountPct  = ($comparePrice && $comparePrice > $price)
            ? (int) round((($comparePrice - $price) / $comparePrice) * 100)
            : 0;

        return [
            'effective_price' => number_format($price, 2, '.', ''),
            'original_price'  => $comparePrice ? number_format($comparePrice, 2, '.', '') : null,
            'discount_pct'    => $discountPct,
            'is_flash_deal'   => false,
            'flash_deal_id'   => null,
        ];
    }

    /**
     * Batch-resolve pricing for a collection of variants.
     * Returns keyed by variant_id for O(1) lookup in transformers.
     */
    public function resolveMany(Collection $variants): array
    {
        // Pre-load all active flash deals for these variant ids in one query
        $variantIds = $variants->pluck('id')->all();

        $flashDeals = EGroceryFlashDeal::with('section')
            ->whereIn('variant_id', $variantIds)
            ->whereHas('section', fn($q) => $q->active())
            ->where(function ($q) {
                $q->whereNull('qty_limit')
                  ->orWhereColumn('qty_sold', '<', 'qty_limit');
            })
            ->get()
            ->keyBy('variant_id');

        $result = [];
        foreach ($variants as $variant) {
            $flashDeal = $flashDeals->get($variant->id);
            if ($flashDeal) {
                $eff  = (float) $flashDeal->deal_price;
                $orig = (float) $variant->price;
                $pct  = $orig > 0 ? (int) round((($orig - $eff) / $orig) * 100) : 0;
                $result[$variant->id] = [
                    'effective_price' => number_format($eff, 2, '.', ''),
                    'original_price'  => number_format($orig, 2, '.', ''),
                    'discount_pct'    => $pct,
                    'is_flash_deal'   => true,
                    'flash_deal_id'   => $flashDeal->id,
                ];
            } else {
                $price = (float) $variant->price;
                $cmp   = $variant->compare_price ? (float) $variant->compare_price : null;
                $pct   = ($cmp && $cmp > $price) ? (int) round((($cmp - $price) / $cmp) * 100) : 0;
                $result[$variant->id] = [
                    'effective_price' => number_format($price, 2, '.', ''),
                    'original_price'  => $cmp ? number_format($cmp, 2, '.', '') : null,
                    'discount_pct'    => $pct,
                    'is_flash_deal'   => false,
                    'flash_deal_id'   => null,
                ];
            }
        }
        return $result;
    }
}
