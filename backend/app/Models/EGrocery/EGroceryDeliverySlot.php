<?php
namespace App\Models\EGrocery;

use Illuminate\Database\Eloquent\Model;

class EGroceryDeliverySlot extends Model
{
    protected $table = 'egrocery_delivery_slots';
    protected $fillable = ['label','day_offset','start_time','end_time','capacity','is_active'];
    protected $casts = ['is_active' => 'boolean'];
}
