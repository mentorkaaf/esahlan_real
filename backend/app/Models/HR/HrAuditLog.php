<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;

class HrAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'hr_audit_logs';

    protected $fillable = [
        'actor_type', 'actor_id', 'actor_name', 'action', 'category',
        'subject_type', 'subject_id', 'employee_id', 'module_id',
        'before', 'after', 'ip', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'before'     => 'array',
        'after'      => 'array',
        'created_at' => 'datetime',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function actor()
    {
        return $this->belongsTo(HrStaff::class, 'actor_id');
    }

    public function employee()
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function module()
    {
        return $this->belongsTo(\App\Models\Module::class, 'module_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeWorkforce($query)
    {
        return $query->where('category', 'workforce');
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForModule($query, int $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    public function scopeActions($query, array $actions)
    {
        return $query->whereIn('action', $actions);
    }

    // ── Computed ──────────────────────────────────────────────────────────────

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
            // Employee lifecycle
            'employee.created'              => 'Employee created',
            'employee.updated'              => 'Employee updated',
            'employee.deleted'              => 'Employee deleted',
            'employee.linked'               => 'App account linked',
            'employee.terminated'           => 'Employee terminated',
            'employee.suspended'            => 'Employee suspended',
            'employee.reactivated'          => 'Employee reactivated',
            // Workforce assignments
            'workforce.assigned'            => 'Assigned to module',
            'workforce.unassigned'          => 'Assignment ended',
            'workforce.suspended'           => 'Assignment suspended',
            'workforce.reactivated'         => 'Assignment reactivated',
            'workforce.position_changed'    => 'Position changed',
            'workforce.department_changed'  => 'Department changed',
            'workforce.role_changed'        => 'Module role changed',
            'workforce.access_granted'      => 'Access level elevated',
            'workforce.access_revoked'      => 'Access level reduced',
            'workforce.expired'             => 'Assignment auto-expired',
            'workforce.all_access_revoked'  => 'All module access revoked',
            // Permissions
            'workforce.permission_granted'  => 'Permission granted',
            'workforce.permission_revoked'  => 'Permission revoked',
            // Other
            'department.created'            => 'Department created',
            'department.updated'            => 'Department updated',
            'position.created'              => 'Position created',
            'position.updated'              => 'Position updated',
            'contract.created'              => 'Contract created',
            'document.uploaded'             => 'Document uploaded',
            'document.deleted'              => 'Document deleted',
            'performance.metrics_recorded'  => 'Performance metrics recorded',
            default => ucfirst(str_replace(['.', '_'], ' ', $this->action)),
        };
    }

    public function getSeverityAttribute(): string
    {
        return match(true) {
            in_array($this->action, [
                'employee.terminated', 'workforce.all_access_revoked',
                'workforce.permission_revoked', 'workforce.access_revoked',
                'workforce.expired',
            ]) => 'high',
            in_array($this->action, [
                'workforce.assigned', 'workforce.unassigned', 'workforce.suspended',
                'workforce.permission_granted', 'workforce.access_granted',
                'workforce.role_changed',
            ]) => 'medium',
            default => 'low',
        };
    }

    public function getSeverityColorAttribute(): string
    {
        return match($this->severity) {
            'high'   => 'red',
            'medium' => 'yellow',
            default  => 'gray',
        };
    }
}
