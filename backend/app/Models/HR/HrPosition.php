<?php

namespace App\Models\HR;

use App\Traits\HR\HrAuditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrPosition extends Model
{
    use HasFactory, SoftDeletes, HrAuditable;

    protected $table = 'hr_positions';

    protected $fillable = [
        'department_id', 'title', 'grade', 'min_salary', 'max_salary', 'description', 'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'min_salary' => 'decimal:2',
        'max_salary' => 'decimal:2',
    ];

    public function department()
    {
        return $this->belongsTo(HrDepartment::class, 'department_id');
    }

    public function employees()
    {
        return $this->hasMany(HrEmployee::class, 'position_id');
    }
}
