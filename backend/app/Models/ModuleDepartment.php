<?php

namespace App\Models;

use App\Models\HR\HrEmployee;
use App\Models\HR\WorkforceAssignment;
use App\Traits\HR\HrAuditable;
use Illuminate\Database\Eloquent\Model;

class ModuleDepartment extends Model
{
    use HrAuditable;

    protected $table = 'module_departments';

    protected $fillable = [
        'module_id',
        'name',
        'description',
        'manager_id',
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

    public function manager()
    {
        return $this->belongsTo(HrEmployee::class, 'manager_id');
    }

    public function workforceAssignments()
    {
        return $this->hasMany(WorkforceAssignment::class, 'module_department_id');
    }

    public function activeAssignments()
    {
        return $this->hasMany(WorkforceAssignment::class, 'module_department_id')
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

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isActive(): bool  { return $this->status === 'active'; }
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
        return match ($this->status) {
            'active'   => 'Active',
            'inactive' => 'Inactive',
            'archived' => 'Archived',
            default    => ucfirst($this->status),
        };
    }
}
