<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CryptoOrder extends Model
{
    protected $table = 'crypto_orders';
    protected $fillable = ['uuid','user_id','coin_id','side','crypto_amount','price_usd','total_usd','fee_usd','fee_pct','payment_method','payment_reference','status','note'];
    protected $casts = ['crypto_amount'=>'float','price_usd'=>'float','total_usd'=>'float','fee_usd'=>'float','fee_pct'=>'float'];
    public function coin() { return $this->belongsTo(ExchangeCoin::class,'coin_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
