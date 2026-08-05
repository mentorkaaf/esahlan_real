<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GlobalOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_number','global_user_id',
        'ship_first_name','ship_last_name','ship_phone',
        'ship_address_line1','ship_address_line2','ship_city',
        'ship_state','ship_zip','ship_country_code','ship_country_name',
        'subtotal','shipping_cost','tax','discount','total','currency',
        'status','payment_status','fulfillment_status',
        'payment_method','payment_intent_id','paypal_order_id',
        'tracking_number','shipping_carrier','tracking_url',
        'estimated_delivery_at','shipped_at','delivered_at',
        'notes','admin_notes',
    ];

    protected $casts = [
        'subtotal'              => 'float',
        'shipping_cost'         => 'float',
        'tax'                   => 'float',
        'discount'              => 'float',
        'total'                 => 'float',
        'estimated_delivery_at' => 'datetime',
        'shipped_at'            => 'datetime',
        'delivered_at'          => 'datetime',
    ];

    public function user()    { return $this->belongsTo(GlobalUser::class, 'global_user_id'); }
    public function items()   { return $this->hasMany(GlobalOrderItem::class); }
    public function payment() { return $this->hasOne(GlobalPayment::class); }

    public static function generateOrderNumber(): string
    {
        return 'ESG-' . strtoupper(substr(uniqid(), -6)) . '-' . date('Ymd');
    }
}
