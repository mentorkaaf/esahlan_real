<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;

class EWReview extends Model
{
    protected $table = 'ewholesale_reviews';
    protected $fillable = ['order_id','buyer_id','supplier_id','product_id','rating','comment','is_visible','hidden_reason','hidden_at'];

    protected $casts = [
        'is_visible' => 'boolean',
        'hidden_at'  => 'datetime',
    ];

    public function scopeVisible($q) { return $q->where('is_visible', true); }

    public function order()    { return $this->belongsTo(EWOrder::class,'order_id'); }
    public function buyer()    { return $this->belongsTo(EWBuyer::class,'buyer_id'); }
    public function supplier() { return $this->belongsTo(EWSupplier::class,'supplier_id'); }
}
