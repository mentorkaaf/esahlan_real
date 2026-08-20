<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;

class EWDispute extends Model
{
    protected $table = 'ewholesale_disputes';

    protected $fillable = [
        'order_id', 'opened_by', 'reason', 'description', 'attachments',
        'status', 'resolution_note', 'resolved_by', 'resolved_at',
        'sla_deadline', 'escalated_at', 'escalated_by',
    ];

    protected $casts = [
        'attachments'  => 'array',
        'resolved_at'  => 'datetime',
        'sla_deadline' => 'datetime',
        'escalated_at' => 'datetime',
    ];

    /** Hours remaining until SLA breach; negative = breached */
    public function slaHoursRemaining(): ?int
    {
        if (!$this->sla_deadline) return null;
        return (int) now()->diffInHours($this->sla_deadline, false);
    }

    public function isSlaBreached(): bool
    {
        return $this->sla_deadline && $this->sla_deadline->isPast() && $this->status === 'open';
    }

    public function order() { return $this->belongsTo(EWOrder::class, 'order_id'); }
}
