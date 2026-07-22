<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CryptoTransaction extends Model
{
    protected $table = 'crypto_transactions';
    protected $fillable = ['uuid','user_id','coin_id','wallet_id','type','amount','fee','balance_before','balance_after','reference_type','reference_id','note','status'];
    protected $casts = ['amount'=>'float','fee'=>'float','balance_before'=>'float','balance_after'=>'float'];
    public function coin() { return $this->belongsTo(ExchangeCoin::class,'coin_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
