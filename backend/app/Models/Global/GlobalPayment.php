<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;

class GlobalPayment extends Model
{
    protected $fillable = [
        'global_order_id','global_user_id','method','transaction_id',
        'amount','currency','status','refunded_amount','refund_id','gateway_response',
    ];
    protected $casts = [
        'amount'           => 'float',
        'refunded_amount'  => 'float',
        'gateway_response' => 'array',
    ];
    public function order() { return $this->belongsTo(GlobalOrder::class, 'global_order_id'); }
    public function user()  { return $this->belongsTo(GlobalUser::class, 'global_user_id'); }
}
