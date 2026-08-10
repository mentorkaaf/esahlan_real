<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;

class GlobalUserCoupon extends Model
{
    protected $fillable = [
        'global_user_id', 'global_coupon_id',
        'collected_at', 'used_at', 'global_order_id', 'is_used',
    ];

    protected $casts = [
        'collected_at' => 'datetime',
        'used_at'      => 'datetime',
        'is_used'      => 'boolean',
    ];

    public function coupon() { return $this->belongsTo(GlobalCoupon::class, 'global_coupon_id'); }
    public function user()   { return $this->belongsTo(GlobalUser::class,   'global_user_id'); }
}
