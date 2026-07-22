<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class P2pOrder extends Model
{
    protected $table = 'p2p_orders';
    protected $fillable = ['uuid','ad_id','buyer_id','seller_id','coin_id','crypto_amount','price_usd','total_usd','payment_method','payment_proof','status','paid_at','released_at','cancelled_at','expires_at','cancel_reason'];
    protected $casts = ['crypto_amount'=>'float','price_usd'=>'float','total_usd'=>'float','paid_at'=>'datetime','released_at'=>'datetime','cancelled_at'=>'datetime','expires_at'=>'datetime'];

    public function ad()     { return $this->belongsTo(P2pAd::class,'ad_id'); }
    public function buyer()  { return $this->belongsTo(User::class,'buyer_id'); }
    public function seller() { return $this->belongsTo(User::class,'seller_id'); }
    public function coin()   { return $this->belongsTo(ExchangeCoin::class,'coin_id'); }
    public function escrow() { return $this->hasOne(P2pEscrow::class,'order_id'); }
    public function dispute(){ return $this->hasOne(P2pDispute::class,'order_id'); }
    public function messages(){ return $this->hasMany(P2pMessage::class,'order_id'); }
}
