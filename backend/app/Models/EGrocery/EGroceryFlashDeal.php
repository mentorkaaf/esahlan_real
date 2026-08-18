<?php
namespace App\Models\EGrocery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EGroceryFlashDeal extends Model
{
    protected $table = 'egrocery_flash_deals';
    protected $fillable = ['section_id','variant_id','deal_price','qty_limit','qty_sold'];
    protected $casts = ['deal_price' => 'decimal:2'];

    public function section(): BelongsTo  { return $this->belongsTo(EGrocerySection::class, 'section_id'); }
    public function variant(): BelongsTo  { return $this->belongsTo(EGroceryProductVariant::class, 'variant_id'); }

    public function isAvailable(): bool
    {
        return $this->qty_limit === null || $this->qty_sold < $this->qty_limit;
    }
}
