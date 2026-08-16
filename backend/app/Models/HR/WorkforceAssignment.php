<?php

namespace App\Models\HR;

use App\Models\Module;
use App\Traits\HR\HrAuditable;
use Illuminate\Database\Eloquent\Model;

class WorkforceAssignment extends Model
{
    use HrAuditable;

    protected $table = 'workforce_assignments';

    protected $fillable = [
        'employee_id',
        'module_id',
        'module_department_id',
        'module_position_id',
        'module_role_id',
        'role_in_module',
        'assignment_type',
        'access_level',
        'start_date',
        'planned_end_date',
        'reporting_manager_id',
        'status',
        'assigned_at',
        'assigned_by',
        'ended_at',
        'notes',
    ];

    protected $casts = [
        'assigned_at'      => 'datetime',
        'ended_at'         => 'datetime',
        'start_date'       => 'date',
        'planned_end_date' => 'date',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    public function employee()
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function module()
    {
        return $this->belongsTo(\App\Models\Module::class, 'module_id');
    }

    public function moduleDepartment()
    {
        return $this->belongsTo(\App\Models\ModuleDepartment::class, 'module_department_id');
    }

    public function modulePosition()
    {
        return $this->belongsTo(\App\Models\ModulePosition::class, 'module_position_id');
    }

    /** Module role granting permissions to this assignment. */
    public function moduleRole()
    {
        return $this->belongsTo(\App\Models\ModuleRole::class, 'module_role_id');
    }

    /** The employee this person reports to within this module assignment. */
    public function reportingManager()
    {
        return $this->belongsTo(HrEmployee::class, 'reporting_manager_id');
    }

    /** HR staff member who created the assignment */
    public function assignedBy()
    {
        return $this->belongsTo(\App\Models\HR\HrStaff::class, 'assigned_by');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForModule($query, int $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(): bool
    {
        return $this->planned_end_date && $this->planned_end_date->isPast() && $this->status === 'active';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active'    => 'green',
            'suspended' => 'yellow',
            'ended'     => 'gray',
            default     => 'gray',
        };
    }

    public function getAssignmentTypeLabelAttribute(): string
    {
        return match ($this->assignment_type) {
            'primary'       => 'Primary',
            'secondary'     => 'Secondary',
            'temporary'     => 'Temporary',
            'acting'        => 'Acting',
            'project_based' => 'Project',
            default         => ucfirst($this->assignment_type ?? 'primary'),
        };
    }

    public function getAssignmentTypeColorAttribute(): string
    {
        return match ($this->assignment_type) {
            'primary'       => 'indigo',
            'secondary'     => 'blue',
            'temporary'     => 'orange',
            'acting'        => 'purple',
            'project_based' => 'teal',
            default         => 'gray',
        };
    }

    public function getAccessLevelLabelAttribute(): string
    {
        return match ($this->access_level) {
            'read_only' => 'Read Only',
            'standard'  => 'Standard',
            'elevated'  => 'Elevated',
            'admin'     => 'Admin',
            default     => ucfirst($this->access_level ?? 'standard'),
        };
    }

    public function getAccessLevelColorAttribute(): string
    {
        return match ($this->access_level) {
            'read_only' => 'gray',
            'standard'  => 'blue',
            'elevated'  => 'orange',
            'admin'     => 'red',
            default     => 'gray',
        };
    }

    public static function assignmentTypes(): array
    {
        return [
            'primary'       => 'Primary',
            'secondary'     => 'Secondary',
            'temporary'     => 'Temporary',
            'acting'        => 'Acting',
            'project_based' => 'Project Based',
        ];
    }

    public static function accessLevels(): array
    {
        return [
            'read_only' => 'Read Only',
            'standard'  => 'Standard',
            'elevated'  => 'Elevated',
            'admin'     => 'Admin',
        ];
    }
}
