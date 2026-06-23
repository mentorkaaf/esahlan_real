<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MovingPricing extends Model {
    protected $table = 'moving_pricing';
    protected $fillable = ['from_district_id','to_district_id','move_type','vehicle_type','base_price','price_per_room','distance_price','is_active'];
    protected $casts = ['base_price'=>'float','is_active'=>'boolean'];
    public function fromDistrict() { return $this->belongsTo(District::class,'from_district_id'); }
    public function toDistrict() { return $this->belongsTo(District::class,'to_district_id'); }
}
