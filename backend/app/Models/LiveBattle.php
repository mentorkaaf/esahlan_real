<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveBattle extends Model
{
    protected $fillable = [
        'battle_room_name', 'status', 'duration_seconds',
        'ends_at', 'winner_host_id', 'invite_id',
    ];

    protected $casts = ['ends_at' => 'datetime'];

    public function participants()
    {
        return $this->hasMany(LiveBattleParticipant::class, 'battle_id');
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_host_id');
    }

    public function invite()
    {
        return $this->belongsTo(LiveBattleInvite::class, 'invite_id');
    }

    /** Find active battle for a given room */
    public static function activeForRoom(int $roomId): ?self
    {
        return self::where('status', 'active')
            ->whereHas('participants', fn($q) => $q->where('live_room_id', $roomId))
            ->with('participants.host.communityProfile')
            ->first();
    }
}
