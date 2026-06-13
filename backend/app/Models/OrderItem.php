<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OrderItem extends Model {
    public $timestamps = false;
    protected $fillable = ['order_id','product_id','variant_id','name','price','quantity','addons','total','meta'];
    protected $casts = ['addons'=>'array','meta'=>'array','price'=>'float','total'=>'float'];
    public function order() { return $this->belongsTo(Order::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
