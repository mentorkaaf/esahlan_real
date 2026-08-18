<?php
namespace App\Models\EGrocery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EGroceryOrderItem extends Model
{
    protected $table = 'egrocery_order_items';
    protected $fillable = [
        'order_id','variant_id','name_snapshot','unit_label_snapshot','unit_price_snapshot',
        'qty','line_total','picked_qty','substitution_variant_id','substitution_status',
    ];
    protected $casts = [
        'unit_price_snapshot' => 'decimal:2',
        'qty'                 => 'decimal:3',
        'line_total'          => 'decimal:2',
        'picked_qty'          => 'decimal:3',
    ];

    public function order(): BelongsTo   { return $this->belongsTo(EGroceryOrder::class, 'order_id'); }
    public function variant(): BelongsTo { return $this->belongsTo(EGroceryProductVariant::class, 'variant_id'); }
    public function substitutionVariant(): BelongsTo
    {
        return $this->belongsTo(EGroceryProductVariant::class, 'substitution_variant_id');
    }
}
