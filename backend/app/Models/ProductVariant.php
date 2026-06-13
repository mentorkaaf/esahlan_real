<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProductVariant extends Model {
    protected $fillable = ['product_id','name','sku','price','stock_quantity','attributes','is_active'];
    protected $casts = ['attributes'=>'array','price'=>'float','is_active'=>'boolean'];
    public function product() { return $this->belongsTo(Product::class); }
}
