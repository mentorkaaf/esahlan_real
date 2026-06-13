<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PushNotificationLog extends Model {
    protected $fillable = ['title','body','image_url','deep_link','data','target_type','target_id','sent_count','sent_by'];
    protected $casts    = ['data' => 'array'];

    public function sentBy()     { return $this->belongsTo(User::class, 'sent_by'); }
    public function targetUser() { return $this->belongsTo(User::class, 'target_id'); }
}
