<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class P2pMessage extends Model
{
    protected $table = 'p2p_messages';
    protected $fillable = ['order_id','sender_id','message','attachment','is_system'];
    protected $casts = ['is_system'=>'boolean'];
    public function sender() { return $this->belongsTo(User::class,'sender_id'); }
}
