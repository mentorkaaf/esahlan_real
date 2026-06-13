<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WithdrawalRequest extends Model {
    protected $fillable = [
        'wallet_id','owner_type','owner_id','amount','method','payment_method',
        'account_number','account_name','account_details','status',
        'reviewed_by','reviewed_at','processed_at','processed_by',
        'transaction_reference','note','admin_note'
    ];
    protected $casts = [
        'amount'=>'float','account_details'=>'array',
        'reviewed_at'=>'datetime','processed_at'=>'datetime'
    ];
    public function wallet() { return $this->belongsTo(Wallet::class); }
    public function reviewer() { return $this->belongsTo(User::class,'reviewed_by'); }
    public function owner() {
        return $this->morphTo('owner');
    }
}
