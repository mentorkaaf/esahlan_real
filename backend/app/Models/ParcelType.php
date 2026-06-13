<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ParcelType extends Model {
    protected $fillable = ['name','description','max_weight_kg','base_fee','fee_per_kg','image','is_active','sort_order'];
    protected $casts = ['max_weight_kg'=>'float','is_active'=>'boolean'];
}
