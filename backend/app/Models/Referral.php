<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Referral extends Model {
    const UPDATED_AT = null;
    protected $fillable = ['referrer_id','referred_id','reward_amount','status','rewarded_at'];
    protected $casts = ['reward_amount'=>'float','rewarded_at'=>'datetime'];
    public function referrer() { return $this->belongsTo(User::class,'referrer_id'); }
    public function referred() { return $this->belongsTo(User::class,'referred_id'); }
}
