<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class HrStaff extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'hr_staff';

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'role', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_active'     => 'boolean',
        'last_login_at' => 'datetime',
        'password'      => 'hashed',
    ];

    // Role capability checks
    public function isManager(): bool  { return $this->role === 'hr_manager'; }
    public function isOfficer(): bool  { return in_array($this->role, ['hr_manager', 'hr_officer']); }
    public function isPayroll(): bool  { return in_array($this->role, ['hr_manager', 'payroll_officer']); }
    public function isRecruiter(): bool{ return in_array($this->role, ['hr_manager', 'recruiter']); }

    public function canDo(string $permission): bool
    {
        return match($permission) {
            'payroll.approve', 'salary.edit', 'employee.terminate' => $this->isManager(),
            'payroll.view', 'payroll.run', 'generate_payroll'     => $this->isPayroll(),
            'recruitment.manage'                                   => $this->isRecruiter(),
            'employee.view', 'employee.edit'                       => true, // all roles
            default                                                => $this->isManager(),
        };
    }

    public function getRoleLabelAttribute(): string
    {
        return match($this->role) {
            'hr_manager'      => 'HR Manager',
            'hr_officer'      => 'HR Officer',
            'payroll_officer' => 'Payroll Officer',
            'recruiter'       => 'Recruiter',
            default           => ucfirst($this->role),
        };
    }
}
