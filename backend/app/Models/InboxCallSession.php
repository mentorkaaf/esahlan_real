<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboxCallSession extends Model
{
    protected $fillable = [
        'uuid', 'conversation_id', 'initiated_by', 'room_name',
        'status', 'answered_at', 'ended_at', 'duration_seconds',
        'offer_sdp', 'answer_sdp', 'user_ice_candidates', 'admin_ice_candidates',
    ];

    protected $casts = [
        'answered_at'          => 'datetime',
        'ended_at'             => 'datetime',
        'user_ice_candidates'  => 'array',
        'admin_ice_candidates' => 'array',
    ];

    public function conversation() { return $this->belongsTo(InboxConversation::class); }
}
