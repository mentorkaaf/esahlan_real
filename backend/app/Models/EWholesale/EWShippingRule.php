<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;

class EWShippingRule extends Model
{
    protected $table = 'ewholesale_shipping_rules';

    protected $fillable = [
        'supplier_id','basis','rate','zone_district_ids','free_over','is_active',
    ];

    protected $casts = [
        'zone_district_ids' => 'array',
        'rate'              => 'decimal:2',
        'free_over'         => 'decimal:2',
        'is_active'         => 'boolean',
    ];
}
