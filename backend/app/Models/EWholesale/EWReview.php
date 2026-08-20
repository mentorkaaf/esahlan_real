<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;

class EWReview extends Model
{
    protected $table = 'ewholesale_reviews';
    protected $fillable = ['order_id','buyer_id','supplier_id','product_id','rating','comment'];

    public function order()    { return $this->belongsTo(EWOrder::class,'order_id'); }
    public function buyer()    { return $this->belongsTo(EWBuyer::class,'buyer_id'); }
    public function supplier() { return $this->belongsTo(EWSupplier::class,'supplier_id'); }
}
