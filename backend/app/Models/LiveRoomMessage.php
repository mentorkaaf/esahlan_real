<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveRoomMessage extends Model
{
    protected $fillable = ['live_room_id', 'user_id', 'message'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function liveRoom()
    {
        return $this->belongsTo(LiveRoom::class);
    }
}
