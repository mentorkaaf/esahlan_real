<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveRoomSetting extends Model
{
    protected $fillable = [
        'live_room_id', 'slow_mode', 'slow_mode_seconds',
        'followers_only', 'min_follow_seconds', 'subscribers_only',
        'blocked_words', 'comments_disabled',
    ];

    protected $casts = [
        'slow_mode'         => 'boolean',
        'followers_only'    => 'boolean',
        'subscribers_only'  => 'boolean',
        'comments_disabled' => 'boolean',
        'blocked_words'     => 'array',
    ];

    public function liveRoom() { return $this->belongsTo(LiveRoom::class); }

    public static function forRoom(int $roomId): self
    {
        return self::firstOrCreate(
            ['live_room_id' => $roomId],
            ['slow_mode' => false, 'followers_only' => false, 'comments_disabled' => false]
        );
    }
}
