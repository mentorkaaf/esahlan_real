<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Commission extends Model {
    public $timestamps = false;
    protected $fillable = ['order_id','vendor_id','module_id','commission_type','commission_rate','order_amount','commission_amount','vendor_earning','status','settled_at'];
    protected $casts = ['commission_rate'=>'float','order_amount'=>'float','commission_amount'=>'float','vendor_earning'=>'float','settled_at'=>'datetime'];
    const CREATED_AT = 'created_at'; const UPDATED_AT = null;
    public function order() { return $this->belongsTo(Order::class); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function module() { return $this->belongsTo(Module::class); }
}
