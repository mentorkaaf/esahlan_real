<?php
namespace App\Models\EGrocery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EGroceryStockMovement extends Model
{
    protected $table = 'egrocery_stock_movements';
    public $timestamps = false;

    protected $fillable = [
        'variant_id','type','qty','stock_before','stock_after','reference','note','actor_id',
    ];

    protected $casts = [
        'qty'          => 'decimal:3',
        'stock_before' => 'decimal:3',
        'stock_after'  => 'decimal:3',
        'created_at'   => 'datetime',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(EGroceryProductVariant::class, 'variant_id');
    }
}
