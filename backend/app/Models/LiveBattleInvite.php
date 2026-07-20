<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveBattleInvite extends Model
{
    protected $fillable = ['from_host_id', 'to_host_id', 'from_room_id', 'status'];

    public function fromHost() { return $this->belongsTo(User::class, 'from_host_id'); }
    public function toHost()   { return $this->belongsTo(User::class, 'to_host_id'); }
    public function fromRoom() { return $this->belongsTo(LiveRoom::class, 'from_room_id'); }
}
