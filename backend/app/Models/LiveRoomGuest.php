<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveRoomGuest extends Model
{
    protected $fillable = [
        'live_room_id', 'user_id', 'status', 'is_muted', 'camera_disabled', 'joined_at', 'left_at',
    ];

    protected $casts = [
        'is_muted'       => 'boolean',
        'camera_disabled' => 'boolean',
        'joined_at'      => 'datetime',
        'left_at'        => 'datetime',
    ];

    public function user()     { return $this->belongsTo(User::class); }
    public function liveRoom() { return $this->belongsTo(LiveRoom::class); }
}
