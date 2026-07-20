<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveChatMute extends Model
{
    protected $fillable = ['live_room_id', 'user_id', 'muted_until'];
    protected $casts = ['muted_until' => 'datetime'];

    public function isActive(): bool
    {
        return $this->muted_until === null || $this->muted_until->isFuture();
    }
}
