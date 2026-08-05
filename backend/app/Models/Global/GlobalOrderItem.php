<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;

class GlobalOrderItem extends Model
{
    protected $fillable = [
        'global_order_id','global_product_id','global_product_variant_id',
        'product_name','variant_name','product_image','sku',
        'quantity','unit_price','total_price','type',
        'supplier_name','supplier_order_id','fulfillment_status',
    ];
    protected $casts = ['unit_price' => 'float', 'total_price' => 'float'];
    public function order()   { return $this->belongsTo(GlobalOrder::class, 'global_order_id'); }
    public function product() { return $this->belongsTo(GlobalProduct::class, 'global_product_id'); }
}
