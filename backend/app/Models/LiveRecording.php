<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveRecording extends Model
{
    protected $fillable = [
        'room_id', 'recording_url', 'thumbnail_url',
        'duration_seconds', 'view_count', 'egress_id', 'status',
    ];

    public function room() { return $this->belongsTo(LiveRoom::class); }
}
