<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CryptoDeposit extends Model
{
    protected $table = 'crypto_deposits';
    protected $fillable = ['uuid','user_id','coin_id','network_id','wallet_id','txhash','amount','fee','confirmations','required_confirmations','status','confirmed_at','admin_note'];
    protected $casts = ['amount'=>'float','fee'=>'float','confirmed_at'=>'datetime'];
    public function coin()    { return $this->belongsTo(ExchangeCoin::class,'coin_id'); }
    public function network() { return $this->belongsTo(ExchangeNetwork::class,'network_id'); }
    public function user()    { return $this->belongsTo(User::class); }
}
