<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftTransaction extends Model
{
    protected $fillable = [
        'sender_id', 'receiver_id', 'gift_id',
        'live_room_id', 'quantity', 'coins_spent',
    ];

    public function sender()   { return $this->belongsTo(User::class, 'sender_id'); }
    public function receiver() { return $this->belongsTo(User::class, 'receiver_id'); }
    public function gift()     { return $this->belongsTo(Gift::class); }
    public function liveRoom() { return $this->belongsTo(LiveRoom::class); }
}
