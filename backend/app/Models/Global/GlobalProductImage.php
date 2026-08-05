<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;

class GlobalProductImage extends Model
{
    protected $fillable = ['product_id','url','sort_order'];
    public function product() { return $this->belongsTo(GlobalProduct::class, 'product_id'); }
}
