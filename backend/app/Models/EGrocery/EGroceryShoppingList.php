<?php
namespace App\Models\EGrocery;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class EGroceryShoppingList extends Model
{
    protected $table = 'egrocery_shopping_lists';
    protected $fillable = ['user_id','name'];

    public function user(): BelongsTo  { return $this->belongsTo(User::class); }
    public function items(): HasMany   { return $this->hasMany(EGroceryShoppingListItem::class, 'list_id'); }
}
