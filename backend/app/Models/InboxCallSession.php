<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboxCallSession extends Model
{
    protected $fillable = [
        'uuid', 'conversation_id', 'initiated_by', 'room_name',
        'status', 'answered_at', 'ended_at', 'duration_seconds',
    ];

    protected $casts = ['answered_at' => 'datetime', 'ended_at' => 'datetime'];

    public function conversation() { return $this->belongsTo(InboxConversation::class); }
}
