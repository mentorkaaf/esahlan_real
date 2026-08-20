<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;

class EWOrderItem extends Model
{
    protected $table = 'ewholesale_order_items';

    protected $fillable = [
        'order_id', 'product_id', 'variant_id', 'name_snapshot', 'unit_snapshot',
        'qty', 'unit_price_snapshot', 'line_total', 'shipped_qty',
    ];

    protected $casts = [
        'qty'                 => 'float',
        'unit_price_snapshot' => 'float',
        'line_total'          => 'float',
        'shipped_qty'         => 'float',
    ];

    public function order()   { return $this->belongsTo(EWOrder::class, 'order_id'); }
    public function product() { return $this->belongsTo(EWProduct::class, 'product_id')->withTrashed(); }
    public function variant() { return $this->belongsTo(EWProductVariant::class, 'variant_id'); }
}
