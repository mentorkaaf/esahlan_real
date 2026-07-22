<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class P2pEscrow extends Model
{
    protected $table = 'p2p_escrow';
    protected $fillable = ['order_id','coin_id','amount','status','released_by','released_at'];
    protected $casts = ['amount'=>'float','released_at'=>'datetime'];
    public function order() { return $this->belongsTo(P2pOrder::class,'order_id'); }
}
