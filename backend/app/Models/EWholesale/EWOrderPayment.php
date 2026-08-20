<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;

class EWOrderPayment extends Model
{
    protected $table = 'ewholesale_order_payments';

    protected $fillable = [
        'order_id', 'type', 'method', 'amount', 'ref', 'status', 'paid_at', 'note', 'recorded_by',
    ];

    protected $casts = [
        'amount'  => 'float',
        'paid_at' => 'datetime',
    ];

    public function order() { return $this->belongsTo(EWOrder::class, 'order_id'); }
}
