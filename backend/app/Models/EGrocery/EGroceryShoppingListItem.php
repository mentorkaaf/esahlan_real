<?php
namespace App\Models\EGrocery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EGroceryShoppingListItem extends Model
{
    protected $table = 'egrocery_shopping_list_items';
    protected $fillable = ['list_id','product_id','variant_id','qty','checked'];
    protected $casts = ['qty' => 'decimal:3', 'checked' => 'boolean'];

    public function list(): BelongsTo    { return $this->belongsTo(EGroceryShoppingList::class, 'list_id'); }
    public function product(): BelongsTo { return $this->belongsTo(EGroceryProduct::class, 'product_id'); }
    public function variant(): BelongsTo { return $this->belongsTo(EGroceryProductVariant::class, 'variant_id'); }
}
