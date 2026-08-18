<?php
namespace App\Services\EGrocery;

use App\Models\EGrocery\{EGroceryProductVariant, EGroceryStockMovement};
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Adjust stock for a variant inside a transaction.
     * qty: positive = add, negative = subtract.
     * Throws \RuntimeException if result would go negative and strict=true.
     */
    public function adjust(
        EGroceryProductVariant $variant,
        float  $qty,
        string $type,           // purchase|sale|adjustment|return|waste
        ?string $reference = null,
        ?string $note      = null,
        ?int    $actorId   = null,
        bool    $strict    = true,
    ): EGroceryProductVariant {
        return DB::transaction(function () use ($variant, $qty, $type, $reference, $note, $actorId, $strict) {
            // Lock the row for update to prevent race conditions
            $variant = EGroceryProductVariant::lockForUpdate()->find($variant->id);

            $stockBefore = (float) $variant->stock_qty;
            $stockAfter  = $stockBefore + $qty;

            if ($strict && $stockAfter < 0) {
                throw new \RuntimeException(
                    "Insufficient stock for variant #{$variant->id}: have {$stockBefore}, need " . abs($qty)
                );
            }

            $variant->stock_qty = max(0, $stockAfter);
            $variant->save();

            EGroceryStockMovement::create([
                'variant_id'   => $variant->id,
                'type'         => $type,
                'qty'          => $qty,
                'stock_before' => $stockBefore,
                'stock_after'  => $variant->stock_qty,
                'reference'    => $reference,
                'note'         => $note,
                'actor_id'     => $actorId,
                'created_at'   => now(),
            ]);

            return $variant->refresh();
        });
    }

    /**
     * Decrement stock for an order (sale). Strict — throws if out of stock.
     */
    public function sale(EGroceryProductVariant $variant, float $qty, string $orderNo, ?int $actorId = null): EGroceryProductVariant
    {
        return $this->adjust($variant, -$qty, 'sale', $orderNo, null, $actorId, strict: true);
    }

    /**
     * Return stock to shelf (order cancellation).
     */
    public function returnStock(EGroceryProductVariant $variant, float $qty, string $orderNo, ?int $actorId = null): EGroceryProductVariant
    {
        return $this->adjust($variant, $qty, 'return', $orderNo, null, $actorId, strict: false);
    }

    /**
     * Get all variants with stock at or below their low_stock_threshold.
     */
    public function lowStockVariants(int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return EGroceryProductVariant::with('product:id,name,name_so', 'unit:id,name')
            ->whereColumn('stock_qty', '<=', 'low_stock_threshold')
            ->where('is_active', true)
            ->orderBy('stock_qty')
            ->limit($limit)
            ->get();
    }

    /**
     * Get out-of-stock active variants.
     */
    public function outOfStockVariants(int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return EGroceryProductVariant::with('product:id,name,name_so')
            ->where('stock_qty', '<=', 0)
            ->where('is_active', true)
            ->limit($limit)
            ->get();
    }
}
