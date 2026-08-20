<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;

class EWActivityLog extends Model
{
    protected $table = 'ewholesale_activity_logs';
    public    $timestamps = false;

    protected $fillable = [
        'actor_type','actor_id','action','subject_type','subject_id','before','after','ip',
    ];

    protected $casts = [
        'before'     => 'array',
        'after'      => 'array',
        'created_at' => 'datetime',
    ];

    /** Convenience: create a log entry without manually building the array */
    public static function record(
        string $action,
        object|null $subject = null,
        array $before = [],
        array $after = [],
        string $actorType = 'system',
        int|null $actorId = null,
    ): self {
        return self::create([
            'actor_type'   => $actorType,
            'actor_id'     => $actorId,
            'action'       => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id'   => $subject?->id,
            'before'       => $before ?: null,
            'after'        => $after  ?: null,
            'ip'           => request()->ip(),
        ]);
    }
}
