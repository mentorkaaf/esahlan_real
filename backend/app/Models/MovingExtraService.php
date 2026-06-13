<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MovingExtraService extends Model {
    protected $fillable = ['name','name_so','price','unit','is_active','sort_order'];
    protected $casts = ['price'=>'float','is_active'=>'boolean'];
}
