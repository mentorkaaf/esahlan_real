<?php
namespace App\Models\EGrocery;

use Illuminate\Database\Eloquent\Model;

class EGroceryDeliveryZone extends Model
{
    protected $table = 'egrocery_delivery_zones';
    protected $fillable = ['name','district_ids','fee','min_order','free_over','is_active'];
    protected $casts = [
        'district_ids' => 'array',
        'fee'          => 'decimal:2',
        'min_order'    => 'decimal:2',
        'free_over'    => 'decimal:2',
        'is_active'    => 'boolean',
    ];
}
