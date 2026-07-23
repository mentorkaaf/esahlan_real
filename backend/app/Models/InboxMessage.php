<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboxMessage extends Model
{
    protected $fillable = [
        'uuid', 'conversation_id', 'sender_id', 'sender_type',
        'type', 'content', 'media_url', 'media_duration', 'metadata',
        'status', 'delivered_at', 'seen_at', 'is_deleted',
    ];

    protected $casts = [
        'metadata'     => 'array',
        'delivered_at' => 'datetime',
        'seen_at'      => 'datetime',
        'is_deleted'   => 'boolean',
    ];

    public function conversation() { return $this->belongsTo(InboxConversation::class); }
    public function sender()       { return $this->belongsTo(User::class, 'sender_id'); }
}
