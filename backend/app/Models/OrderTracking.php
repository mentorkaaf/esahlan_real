<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OrderTracking extends Model {
    const UPDATED_AT = null;
    protected $fillable = ['order_id','deliveryman_id','latitude','longitude'];
    protected $casts = ['latitude'=>'float','longitude'=>'float'];
    public function order() { return $this->belongsTo(Order::class); }
}
