<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveGuestRequest extends Model
{
    protected $fillable = ['live_room_id', 'user_id', 'status'];

    public function user()     { return $this->belongsTo(User::class); }
    public function liveRoom() { return $this->belongsTo(LiveRoom::class); }
}
