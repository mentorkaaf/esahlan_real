<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CouponUsage extends Model {
    const UPDATED_AT = null;
    protected $fillable = ['coupon_id','user_id','order_id','discount_amount'];
    protected $casts = ['discount_amount'=>'float'];
    public function coupon() { return $this->belongsTo(Coupon::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function order() { return $this->belongsTo(Order::class); }
}
