<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveRoomReport extends Model
{
    protected $fillable = ['live_room_id', 'reporter_id', 'reason', 'description', 'status'];

    public function room()     { return $this->belongsTo(LiveRoom::class, 'live_room_id'); }
    public function reporter() { return $this->belongsTo(User::class, 'reporter_id'); }
}
