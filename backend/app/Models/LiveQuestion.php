<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveQuestion extends Model
{
    protected $fillable = ['room_id', 'user_id', 'username', 'avatar', 'question', 'status'];

    public function room() { return $this->belongsTo(LiveRoom::class); }
    public function user() { return $this->belongsTo(User::class); }
}
