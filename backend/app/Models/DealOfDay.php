<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DealOfDay extends Model {
    protected $table    = 'deals_of_day';
    protected $fillable = ['product_id','badge','discount_type','discount_value','ends_at','sort_order','is_active'];
    protected $casts    = ['is_active' => 'boolean', 'discount_value' => 'float', 'ends_at' => 'datetime'];

    public function product() {
        return $this->belongsTo(Product::class);
    }
}
