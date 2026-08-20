<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo};

class EWBuyerPriceList extends Model
{
    protected $table = 'ewholesale_buyer_price_lists';
    protected $fillable = ['buyer_id','price_list_id'];

    public function buyer(): BelongsTo     { return $this->belongsTo(EWBuyer::class, 'buyer_id'); }
    public function priceList(): BelongsTo { return $this->belongsTo(EWPriceList::class, 'price_list_id'); }
}
