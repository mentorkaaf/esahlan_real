<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveGoal extends Model
{
    protected $fillable = ['room_id', 'type', 'title', 'target', 'current', 'status'];

    public function room() { return $this->belongsTo(LiveRoom::class); }

    public function toArray(): array
    {
        return [
            'id'       => $this->id,
            'type'     => $this->type,
            'title'    => $this->title,
            'target'   => $this->target,
            'current'  => $this->current,
            'percent'  => $this->target > 0 ? min(100, (int)(($this->current / $this->target) * 100)) : 0,
            'status'   => $this->status,
        ];
    }
}
