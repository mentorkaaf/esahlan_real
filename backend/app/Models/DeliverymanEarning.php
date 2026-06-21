<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DeliverymanEarning extends Model {
    public $timestamps = false;
    protected $fillable = ['deliveryman_id','order_id','type','amount','note'];
    protected $casts = ['amount' => 'float'];

    public function deliveryman() { return $this->belongsTo(Deliveryman::class); }
    public function order() { return $this->belongsTo(Order::class); }
}
