<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CommunityNotification extends Model {
    protected $fillable = ['user_id','actor_id','type','notifiable_type','notifiable_id','data','is_read'];
    protected $casts = ['is_read'=>'boolean','data'=>'array'];

    public function user() { return $this->belongsTo(User::class); }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
}