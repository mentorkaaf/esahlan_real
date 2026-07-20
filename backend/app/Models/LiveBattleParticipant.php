<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveBattleParticipant extends Model
{
    protected $fillable = ['battle_id', 'host_id', 'live_room_id', 'score', 'rank'];

    public function battle()   { return $this->belongsTo(LiveBattle::class, 'battle_id'); }
    public function host()     { return $this->belongsTo(User::class, 'host_id'); }
    public function liveRoom() { return $this->belongsTo(LiveRoom::class, 'live_room_id'); }
}
