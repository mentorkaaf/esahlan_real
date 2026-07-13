<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event', 'severity', 'user_id', 'user_identifier',
        'ip_address', 'user_agent', 'metadata', 'created_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
}
