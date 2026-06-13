<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Addon extends Model {
    protected $fillable = ['vendor_id','name','price','is_active'];
    protected $casts = ['price'=>'float','is_active'=>'boolean'];
    public function vendor() { return $this->belongsTo(Vendor::class); }
}
