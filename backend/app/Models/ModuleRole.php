<?php

namespace App\Models;

use App\Models\HR\WorkforceAssignment;
use App\Traits\HR\HrAuditable;
use Illuminate\Database\Eloquent\Model;

class ModuleRole extends Model
{
    use HrAuditable;

    protected $table = 'module_roles';

    protected $fillable = [
        'module_id', 'name', 'slug', 'description',
        'is_default', 'is_system', 'status', 'sort_order',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_system'  => 'boolean',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    /** All permissions granted by this module role. */
    public function permissions()
    {
        return $this->belongsToMany(
            Permission::class,
            'module_role_permissions',
            'module_role_id',
            'permission_id'
        );
    }

    /** Workforce assignments using this role. */
    public function workforceAssignments()
    {
        return $this->hasMany(WorkforceAssignment::class);
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

    // ── Accessors / Helpers ───────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active'   => 'green',
            'archived' => 'gray',
            default    => 'gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active'   => 'Active',
            'archived' => 'Archived',
            default    => ucfirst($this->status),
        };
    }

    /**
     * The 9 permission groups in display order.
     */
    public static function permissionGroups(): array
    {
        return [
            'dashboard'  => 'Dashboard',
            'employees'  => 'Employees',
            'orders'     => 'Orders',
            'vendors'    => 'Vendors',
            'customers'  => 'Customers',
            'reports'    => 'Reports',
            'settings'   => 'Settings',
            'finance'    => 'Finance',
            'operations' => 'Module Operations',
        ];
    }

    /**
     * Permission slugs that belong to this role, keyed for fast lookup.
     */
    public function permissionSlugs(): array
    {
        return $this->permissions->pluck('slug')->all();
    }
}
