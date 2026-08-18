<?php
namespace App\Models\EGrocery;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EGroceryFavorite extends Model
{
    protected $table = 'egrocery_favorites';
    public $timestamps = false;
    protected $fillable = ['user_id','product_id'];
    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function product(): BelongsTo { return $this->belongsTo(EGroceryProduct::class, 'product_id'); }
}
