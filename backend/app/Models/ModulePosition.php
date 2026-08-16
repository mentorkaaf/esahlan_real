<?php

namespace App\Models;

use App\Models\HR\HrPosition;
use App\Models\HR\WorkforceAssignment;
use App\Traits\HR\HrAuditable;
use Illuminate\Database\Eloquent\Model;

class ModulePosition extends Model
{
    use HrAuditable;

    protected $table = 'module_positions';

    protected $fillable = [
        'module_id',
        'module_department_id',
        'hr_position_id',
        'name',
        'description',
        'level',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function moduleDepartment()
    {
        return $this->belongsTo(ModuleDepartment::class, 'module_department_id');
    }

    /** The company HR position this module position is equivalent to (reuse). */
    public function hrPosition()
    {
        return $this->belongsTo(HrPosition::class, 'hr_position_id');
    }

    public function workforceAssignments()
    {
        return $this->hasMany(WorkforceAssignment::class, 'module_position_id');
    }

    public function activeAssignments()
    {
        return $this->hasMany(WorkforceAssignment::class, 'module_position_id')
                    ->where('status', 'active');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForModule($query, int $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    public function scopeForDepartment($query, int $deptId)
    {
        return $query->where('module_department_id', $deptId);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isActive(): bool   { return $this->status === 'active'; }
    public function isArchived(): bool { return $this->status === 'archived'; }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active'   => 'green',
            'inactive' => 'yellow',
            'archived' => 'gray',
            default    => 'gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return ucfirst($this->status);
    }

    public function getLevelBadgeColorAttribute(): string
    {
        return match ($this->level) {
            'junior'  => 'blue',
            'mid'     => 'indigo',
            'senior'  => 'purple',
            'lead'    => 'orange',
            'manager' => 'red',
            default   => 'gray',
        };
    }

    public static function levels(): array
    {
        return ['junior', 'mid', 'senior', 'lead', 'manager'];
    }
}
