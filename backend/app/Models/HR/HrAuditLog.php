<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;

class HrAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'hr_audit_logs';

    protected $fillable = [
        'actor_type', 'actor_id', 'actor_name', 'action',
        'subject_type', 'subject_id', 'before', 'after',
        'ip', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'before'     => 'array',
        'after'      => 'array',
        'created_at' => 'datetime',
    ];

    // Human-readable diff of before/after
    public function getDiffAttribute(): array
    {
        $before = $this->before ?? [];
        $after  = $this->after  ?? [];
        $diff   = [];

        foreach ($after as $key => $newVal) {
            $oldVal = $before[$key] ?? null;
            if ($oldVal !== $newVal) {
                $diff[$key] = ['from' => $oldVal, 'to' => $newVal];
            }
        }
        return $diff;
    }

    public function getActionLabelAttribute(): string
    {
        return match($this->action) {
            'employee.created'     => 'Employee created',
            'employee.updated'     => 'Employee updated',
            'employee.deleted'     => 'Employee deleted',
            'employee.linked'      => 'App account linked',
            'department.created'   => 'Department created',
            'department.updated'   => 'Department updated',
            'position.created'     => 'Position created',
            'position.updated'     => 'Position updated',
            'contract.created'     => 'Contract created',
            'document.uploaded'    => 'Document uploaded',
            'document.deleted'     => 'Document deleted',
            default                => ucfirst(str_replace('.', ' ', $this->action)),
        };
    }
}
