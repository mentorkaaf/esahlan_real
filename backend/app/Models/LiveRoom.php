<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveRoom extends Model
{
    protected $fillable = [
        'host_id', 'title', 'room_name', 'thumbnail',
        'status', 'viewer_count', 'peak_viewers', 'ended_at',
    ];

    protected $casts = ['ended_at' => 'datetime'];

    public function host()    { return $this->belongsTo(User::class, 'host_id'); }
    public function viewers() { return $this->hasMany(LiveRoomViewer::class); }
    public function gifts()   { return $this->hasMany(GiftTransaction::class); }
}
