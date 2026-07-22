<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CryptoWithdrawal extends Model
{
    protected $table = 'crypto_withdrawals';
    protected $fillable = ['uuid','user_id','coin_id','network_id','to_address','amount','fee','net_amount','txhash','status','approved_by','approved_at','processed_at','admin_note'];
    protected $casts = ['amount'=>'float','fee'=>'float','net_amount'=>'float','approved_at'=>'datetime','processed_at'=>'datetime'];
    public function coin()    { return $this->belongsTo(ExchangeCoin::class,'coin_id'); }
    public function network() { return $this->belongsTo(ExchangeNetwork::class,'network_id'); }
    public function user()    { return $this->belongsTo(User::class); }
}
