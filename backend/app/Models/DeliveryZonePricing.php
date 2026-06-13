<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DeliveryZonePricing extends Model {
    protected $table = 'delivery_zone_pricing';
    protected $fillable = ['module_id','from_district_id','to_district_id','base_price','price_per_kg','per_km_price','is_active'];
    protected $casts = ['base_price'=>'float','per_km_price'=>'float','is_active'=>'boolean'];
    public function module() { return $this->belongsTo(Module::class); }
    public function fromDistrict() { return $this->belongsTo(District::class,'from_district_id'); }
    public function toDistrict() { return $this->belongsTo(District::class,'to_district_id'); }
}
