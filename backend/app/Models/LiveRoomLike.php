<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveRoomLike extends Model
{
    protected $fillable = ['live_room_id', 'user_id'];
}
