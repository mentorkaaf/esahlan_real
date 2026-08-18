<?php
namespace App\Models\EGrocery;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EGroceryReview extends Model
{
    protected $table = 'egrocery_reviews';
    protected $fillable = ['user_id','product_id','order_id','rating','comment','is_approved'];
    protected $casts = ['is_approved' => 'boolean', 'rating' => 'integer'];

    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function product(): BelongsTo { return $this->belongsTo(EGroceryProduct::class, 'product_id'); }
    public function order(): BelongsTo   { return $this->belongsTo(EGroceryOrder::class, 'order_id'); }
}
