<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CommunityChatMember extends Model {
    protected $fillable = ['chat_id','user_id','role','last_read_at','is_muted'];
    protected $casts = ['last_read_at'=>'datetime','is_muted'=>'boolean'];
    public function user() { return $this->belongsTo(User::class); }
}