<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveRoomViewer extends Model
{
    public $timestamps = false;
    protected $fillable = ['live_room_id', 'user_id', 'joined_at', 'left_at'];
    protected $casts    = ['joined_at' => 'datetime', 'left_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
}
