<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PaymentTransaction extends Model {
    protected $fillable = ['reference','order_id','user_id','gateway','amount','currency','status','gateway_reference','gateway_response'];
    protected $casts = ['amount'=>'float','gateway_response'=>'array'];
    public function order() { return $this->belongsTo(Order::class); }
    public function user() { return $this->belongsTo(User::class); }
}
