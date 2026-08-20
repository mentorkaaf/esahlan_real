<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;

class EWInquiry extends Model
{
    protected $table = 'ewholesale_inquiries';
    protected $fillable = ['buyer_id','supplier_id','product_id','qty','message','chat_id'];
    protected $casts = ['qty' => 'float'];

    public function buyer()    { return $this->belongsTo(EWBuyer::class,'buyer_id'); }
    public function supplier() { return $this->belongsTo(EWSupplier::class,'supplier_id'); }
    public function product()  { return $this->belongsTo(EWProduct::class,'product_id')->withTrashed(); }
    public function quotes()   { return $this->hasMany(EWQuote::class,'inquiry_id'); }
}
