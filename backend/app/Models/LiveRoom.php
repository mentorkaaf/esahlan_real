<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveRoom extends Model
{
    protected $fillable = [
        'host_id', 'title', 'room_name', 'thumbnail',
        'category', 'tags', 'status', 'viewer_count', 'peak_viewers',
        'total_coins_earned', 'ended_at',
    ];

    protected $casts = [
        'ended_at' => 'datetime',
        'tags'     => 'array',
    ];

    public function host()               { return $this->belongsTo(User::class, 'host_id'); }
    public function viewers()            { return $this->hasMany(LiveRoomViewer::class); }
    public function gifts()              { return $this->hasMany(GiftTransaction::class); }
    public function battleParticipants() { return $this->hasMany(LiveBattleParticipant::class); }
}
