<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
    protected $fillable = [
        'caller_id', 'receiver_id', 'type', 'status',
        'room_name', 'accepted_at', 'ended_at', 'duration',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'ended_at'    => 'datetime',
    ];

    public function caller()   { return $this->belongsTo(User::class, 'caller_id'); }
    public function receiver() { return $this->belongsTo(User::class, 'receiver_id'); }
}
