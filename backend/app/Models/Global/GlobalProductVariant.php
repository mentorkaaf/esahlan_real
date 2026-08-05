<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;

class GlobalProductVariant extends Model
{
    protected $fillable = ['product_id','name','value','price_modifier','stock','sku','image'];
    protected $casts    = ['price_modifier' => 'float'];
    public function product() { return $this->belongsTo(GlobalProduct::class, 'product_id'); }
}
