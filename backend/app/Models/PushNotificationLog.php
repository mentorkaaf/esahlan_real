<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PushNotificationLog extends Model {
    protected $fillable = ['title','body','data','target_type','target_id','sent_count','sent_by'];
    protected $casts = ['data'=>'array'];
}
