<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class P2pAd extends Model
{
    protected $table = 'p2p_ads';
    protected $fillable = ['uuid','user_id','coin_id','type','amount','remaining','price_usd','payment_methods','min_order_usd','max_order_usd','terms','auto_reply_minutes','status','completed_count','total_orders','is_merchant'];
    protected $casts = ['amount'=>'float','remaining'=>'float','price_usd'=>'float','min_order_usd'=>'float','max_order_usd'=>'float','payment_methods'=>'array','is_merchant'=>'boolean'];

    public function user() { return $this->belongsTo(User::class); }
    public function coin() { return $this->belongsTo(ExchangeCoin::class,'coin_id'); }
    public function orders() { return $this->hasMany(P2pOrder::class,'ad_id'); }
}
