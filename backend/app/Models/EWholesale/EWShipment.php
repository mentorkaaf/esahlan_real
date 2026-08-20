<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;

class EWShipment extends Model
{
    protected $table = 'ewholesale_shipments';

    protected $fillable = [
        'order_id', 'shipment_no', 'items', 'status', 'dispatch_id',
        'dispatched_at', 'delivered_at', 'note',
    ];

    protected $casts = [
        'items'        => 'array',
        'dispatched_at'=> 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order() { return $this->belongsTo(EWOrder::class, 'order_id'); }
}
