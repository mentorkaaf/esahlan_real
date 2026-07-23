<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboxConversation extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'agent_id', 'module', 'subject',
        'status', 'priority', 'last_message', 'last_message_at',
        'unread_user', 'unread_agent', 'resolved_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'resolved_at'     => 'datetime',
    ];

    public function user()    { return $this->belongsTo(User::class); }
    public function agent()   { return $this->belongsTo(User::class, 'agent_id'); }
    public function messages(){ return $this->hasMany(InboxMessage::class, 'conversation_id')->orderBy('created_at'); }
    public function lastMsg() { return $this->hasOne(InboxMessage::class, 'conversation_id')->latestOfMany(); }
    public function calls()   { return $this->hasMany(InboxCallSession::class, 'conversation_id'); }
}
