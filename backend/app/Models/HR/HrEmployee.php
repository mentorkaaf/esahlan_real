<?php

namespace App\Models\HR;

use App\Models\User;
use App\Traits\HR\HrAuditable;
// WorkforceAssignment used in type hints below (same namespace)
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrEmployee extends Model
{
    use HasFactory, SoftDeletes, HrAuditable;

    protected $table = 'hr_employees';

    // Sensitive fields — masked for non-authorized roles
    public const SENSITIVE_FIELDS = ['base_salary', 'national_id', 'bank_account', 'mobile_money_number'];

    protected $fillable = [
        'employee_no', 'user_id', 'department_id', 'position_id',
        'first_name', 'middle_name', 'last_name', 'gender', 'dob', 'national_id',
        'phone', 'email', 'district', 'address', 'photo',
        'employment_type', 'status', 'hire_date', 'probation_end',
        'base_salary', 'bank_account', 'mobile_money_number', 'emergency_contact',
        'active_workspace_id', 'workspace_switched_at',
    ];

    protected $casts = [
        'dob'                    => 'date',
        'hire_date'              => 'date',
        'probation_end'          => 'date',
        'base_salary'            => 'decimal:2',
        'emergency_contact'      => 'array',
        'workspace_switched_at'  => 'datetime',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function department()
    {
        return $this->belongsTo(HrDepartment::class, 'department_id');
    }

    public function position()
    {
        return $this->belongsTo(HrPosition::class, 'position_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contracts()
    {
        return $this->hasMany(HrContract::class, 'employee_id');
    }

    public function documents()
    {
        return $this->hasMany(HrDocument::class, 'employee_id');
    }

    public function attendance()
    {
        return $this->hasMany(\App\Models\HR\HrAttendance::class, 'employee_id');
    }

    public function shifts()
    {
        return $this->hasMany(\App\Models\HR\HrEmployeeShift::class, 'employee_id');
    }

    public function leaveBalances()
    {
        return $this->hasMany(\App\Models\HR\HrLeaveBalance::class, 'employee_id');
    }

    public function leaveRequests()
    {
        return $this->hasMany(\App\Models\HR\HrLeaveRequest::class, 'employee_id');
    }

    public function goals()
    {
        return $this->hasMany(\App\Models\HR\HrGoal::class, 'employee_id');
    }

    public function reviews()
    {
        return $this->hasMany(\App\Models\HR\HrReview::class, 'employee_id');
    }

    public function payslips()
    {
        return $this->hasMany(\App\Models\HR\HrPayslip::class, 'employee_id');
    }

    public function commissions()
    {
        return $this->hasMany(\App\Models\HR\HrCommission::class, 'employee_id');
    }

    public function disciplinaryCases()
    {
        return $this->hasMany(\App\Models\HR\HrDisciplinaryCase::class, 'employee_id');
    }

    public function workforceAssignments()
    {
        return $this->hasMany(\App\Models\HR\WorkforceAssignment::class, 'employee_id');
    }

    public function activeWorkforceAssignments()
    {
        return $this->hasMany(\App\Models\HR\WorkforceAssignment::class, 'employee_id')
                    ->where('status', 'active');
    }

    /** The assignment that is currently the employee's active workspace. */
    public function activeWorkspace()
    {
        return $this->belongsTo(\App\Models\HR\WorkforceAssignment::class, 'active_workspace_id');
    }

    /**
     * Switch to a different active workspace.
     * The given assignment must be an active assignment belonging to this employee.
     * If the employee has only one active assignment, it auto-activates on first call.
     */
    public function switchWorkspace(WorkforceAssignment $assignment): bool
    {
        if ($assignment->employee_id !== $this->id || $assignment->status !== 'active') {
            return false;
        }

        $this->update([
            'active_workspace_id'   => $assignment->id,
            'workspace_switched_at' => now(),
        ]);

        return true;
    }

    /**
     * Returns the effective active workspace assignment.
     * If none is set, defaults to the primary assignment (or the first active one).
     * Auto-saves the default so it's set going forward.
     */
    public function resolvedWorkspace(): ?WorkforceAssignment
    {
        if ($this->active_workspace_id) {
            return $this->activeWorkspace;
        }

        // Auto-resolve: primary first, then any active
        $default = $this->activeWorkforceAssignments()
            ->with(['module', 'moduleRole'])
            ->orderByRaw("FIELD(assignment_type, 'primary', 'secondary', 'temporary', 'acting', 'project_based')")
            ->first();

        if ($default) {
            $this->update([
                'active_workspace_id'   => $default->id,
                'workspace_switched_at' => now(),
            ]);
            $this->setRelation('activeWorkspace', $default);
        }

        return $default;
    }

    public function auditLogs()
    {
        return HrAuditLog::where('subject_type', self::class)
            ->where('subject_id', $this->id)
            ->orderByDesc('created_at');
    }

    // ── Accessors ──────────────────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'active'     => 'green',
            'probation'  => 'yellow',
            'suspended'  => 'orange',
            'terminated' => 'red',
            'resigned'   => 'gray',
            default      => 'gray',
        };
    }

    public function getInitialsAttribute(): string
    {
        return strtoupper(substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1));
    }

    // ── Auto-generate employee_no ──────────────────────────────────────────────

    public static function generateEmployeeNo(): string
    {
        $last = static::withTrashed()->orderByDesc('id')->value('employee_no');
        $seq  = $last ? ((int) substr($last, -4)) + 1 : 1;
        return 'ESH-EMP-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
