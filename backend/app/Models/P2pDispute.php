<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class P2pDispute extends Model
{
    protected $table = 'p2p_disputes';
    protected $fillable = ['order_id','opened_by','reason','evidence','status','resolved_by','resolution','admin_note','resolved_at'];
    protected $casts = ['evidence'=>'array','resolved_at'=>'datetime'];
    public function order()      { return $this->belongsTo(P2pOrder::class,'order_id'); }
    public function opener()     { return $this->belongsTo(User::class,'opened_by'); }
    public function resolver()   { return $this->belongsTo(User::class,'resolved_by'); }
}
