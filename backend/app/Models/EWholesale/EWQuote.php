<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;

class EWQuote extends Model
{
    protected $table = 'ewholesale_quotes';

    protected $fillable = [
        'inquiry_id','rfq_quote_id','buyer_id','supplier_id',
        'lines','subtotal','delivery_fee','total','payment_term',
        'valid_until','status','counter_of_id','accepted_at',
    ];

    protected $casts = [
        'lines'        => 'array',
        'subtotal'     => 'float',
        'delivery_fee' => 'float',
        'total'        => 'float',
        'valid_until'  => 'date',
        'accepted_at'  => 'datetime',
    ];

    public function buyer()    { return $this->belongsTo(EWBuyer::class,'buyer_id'); }
    public function supplier() { return $this->belongsTo(EWSupplier::class,'supplier_id'); }
    public function counterOf() { return $this->belongsTo(EWQuote::class,'counter_of_id'); }
    public function counters()  { return $this->hasMany(EWQuote::class,'counter_of_id'); }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }
}
