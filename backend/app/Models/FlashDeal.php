<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class FlashDeal extends Model {
    protected $fillable = ['title','subtitle','banner','discount_type','discount_value','starts_at','ends_at','is_active'];
    protected $casts    = ['is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'discount_value' => 'float'];

    public function products() {
        return $this->belongsToMany(Product::class, 'flash_deal_products', 'flash_deal_id', 'product_id')
                    ->withPivot('override_price');
    }
}
