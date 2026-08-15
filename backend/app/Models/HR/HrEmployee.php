<?php

namespace App\Models\HR;

use App\Models\User;
use App\Traits\HR\HrAuditable;
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
    ];

    protected $casts = [
        'dob'               => 'date',
        'hire_date'         => 'date',
        'probation_end'     => 'date',
        'base_salary'       => 'decimal:2',
        'emergency_contact' => 'array',
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
