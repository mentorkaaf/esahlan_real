<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EWPriceList extends Model
{
    protected $table = 'ewholesale_price_lists';
    protected $fillable = ['name','discount_percent','is_active'];
    protected $casts = ['discount_percent' => 'decimal:2', 'is_active' => 'boolean'];

    public function buyerLinks(): HasMany { return $this->hasMany(EWBuyerPriceList::class, 'price_list_id'); }
}
