<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Transaction extends Model {
    protected $fillable = ['uuid','wallet_id','reference_type','reference_id','type','amount','balance_before','balance_after','note','payment_method','payment_reference','status'];
    protected $casts = ['amount'=>'float','balance_before'=>'float','balance_after'=>'float'];
    public function wallet() { return $this->belongsTo(Wallet::class); }
}
