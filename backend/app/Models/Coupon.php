<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Coupon extends Model {
    protected $fillable = ['code','title','description','type','value','min_order_amount','max_discount','usage_limit','usage_per_user','used_count','module_id','vendor_id','module_slug','is_active','starts_at','ends_at','category_ids'];
    protected $casts = ['is_active'=>'boolean','starts_at'=>'datetime','ends_at'=>'datetime','value'=>'float','min_order_amount'=>'float','max_discount'=>'float','category_ids'=>'array'];
    public function module() { return $this->belongsTo(Module::class); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function usages() { return $this->hasMany(CouponUsage::class); }
    public function orders() { return $this->hasMany(Order::class); }
}
