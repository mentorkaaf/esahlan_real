<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;

class EWRfq extends Model
{
    protected $table = 'ewholesale_rfqs';

    protected $fillable = [
        'buyer_id', 'title', 'category_id', 'description', 'qty', 'unit',
        'target_price', 'needed_by', 'attachments', 'status', 'expires_at',
    ];

    protected $casts = [
        'qty'         => 'float',
        'target_price'=> 'float',
        'attachments' => 'array',
        'needed_by'   => 'date',
        'expires_at'  => 'datetime',
    ];

    public function buyer()  { return $this->belongsTo(EWBuyer::class, 'buyer_id'); }
    public function quotes() { return $this->hasMany(EWRfqQuote::class, 'rfq_id'); }
    public function category(){ return $this->belongsTo(EWCategory::class, 'category_id'); }
}
