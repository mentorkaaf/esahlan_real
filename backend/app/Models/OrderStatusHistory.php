<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OrderStatusHistory extends Model {
    // Actual DB table is order_status_history (not the Eloquent default _histories)
    protected $table = 'order_status_history';
    const UPDATED_AT = null;
    protected $fillable = ['order_id','status','note','changed_by','actor_id','actor_type'];
    public function order() { return $this->belongsTo(Order::class); }
}
