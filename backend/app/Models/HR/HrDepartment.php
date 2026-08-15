<?php

namespace App\Models\HR;

use App\Traits\HR\HrAuditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrDepartment extends Model
{
    use HasFactory, SoftDeletes, HrAuditable;

    protected $table = 'hr_departments';

    protected $fillable = [
        'name', 'code', 'parent_id', 'manager_employee_id', 'description', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function parent()
    {
        return $this->belongsTo(HrDepartment::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(HrDepartment::class, 'parent_id');
    }

    public function employees()
    {
        return $this->hasMany(HrEmployee::class, 'department_id');
    }

    public function positions()
    {
        return $this->hasMany(HrPosition::class, 'department_id');
    }

    public function manager()
    {
        return $this->belongsTo(HrEmployee::class, 'manager_employee_id');
    }

    public function getActiveEmployeeCountAttribute(): int
    {
        return $this->employees()->where('status', 'active')->count();
    }
}
