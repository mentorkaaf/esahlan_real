<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;

class GlobalWishlist extends Model
{
    protected $fillable = ['global_user_id','global_product_id'];
    public function product() { return $this->belongsTo(GlobalProduct::class, 'global_product_id'); }
}
