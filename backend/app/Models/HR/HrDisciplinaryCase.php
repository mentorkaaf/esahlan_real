<?php

namespace App\Models\HR;

use App\Traits\HR\HrAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrDisciplinaryCase extends Model
{
    use HrAuditable, SoftDeletes;

    protected $table = 'hr_disciplinary_cases';

    protected $fillable = [
        'employee_id','opened_by','category','severity','title','description',
        'status','outcome','investigation_notes','closed_by','closed_at',
    ];

    protected $casts = ['closed_at' => 'datetime'];

    public function employee()    { return $this->belongsTo(HrEmployee::class); }
    public function openedBy()    { return $this->belongsTo(HrStaff::class, 'opened_by'); }
    public function closedBy()    { return $this->belongsTo(HrStaff::class, 'closed_by'); }
    public function warnings()    { return $this->hasMany(HrWarning::class, 'case_id'); }

    public function getSeverityBadgeClass(): string
    {
        return match($this->severity) {
            'minor'    => 'bg-yellow-100 text-yellow-700',
            'moderate' => 'bg-orange-100 text-orange-700',
            'major'    => 'bg-red-100 text-red-700',
            default    => 'bg-gray-100 text-gray-500',
        };
    }

    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            'open'         => 'bg-blue-100 text-blue-700',
            'investigating'=> 'bg-purple-100 text-purple-700',
            'closed'       => 'bg-gray-100 text-gray-500',
            default        => 'bg-gray-100 text-gray-500',
        };
    }

    public function getOutcomeLabel(): string
    {
        return match($this->outcome) {
            'verbal_warning'  => 'Verbal Warning',
            'written_warning' => 'Written Warning',
            'suspension'      => 'Suspension',
            'termination'     => 'Termination',
            'dismissed'       => 'Case Dismissed',
            default           => '—',
        };
    }
}
