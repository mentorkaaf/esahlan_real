<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LaundryItem extends Model {
    protected $fillable = ['name','name_so','normal_price','express_price','normal_days','express_hours','image','is_active','sort_order'];
    protected $casts = ['normal_price'=>'float','express_price'=>'float','is_active'=>'boolean'];
}
