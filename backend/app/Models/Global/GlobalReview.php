<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;

class GlobalReview extends Model
{
    protected $fillable = [
        'global_product_id','global_user_id','global_order_id',
        'rating','title','body','images','is_approved','is_verified_purchase',
    ];
    protected $casts = [
        'images'               => 'array',
        'is_approved'          => 'boolean',
        'is_verified_purchase' => 'boolean',
    ];
    public function product() { return $this->belongsTo(GlobalProduct::class, 'global_product_id'); }
    public function user()    { return $this->belongsTo(GlobalUser::class, 'global_user_id'); }
}
