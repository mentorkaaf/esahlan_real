<?php

namespace App\Models\HR;

use App\Traits\HR\HrAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrApplicant extends Model
{
    use HrAuditable, SoftDeletes;

    protected $table = 'hr_applicants';

    protected $fillable = [
        'job_posting_id', 'name', 'email', 'phone', 'cv_path',
        'stage', 'stage_changed_at', 'rating', 'notes', 'source',
        'is_hired', 'employee_id',
    ];

    protected $casts = [
        'stage_changed_at' => 'datetime',
        'is_hired'         => 'boolean',
        'rating'           => 'integer',
    ];

    // Stage machine
    const STAGES = ['applied', 'screening', 'interview', 'offer', 'hired', 'rejected'];

    public function nextStage(): ?string
    {
        $idx = array_search($this->stage, self::STAGES);
        if ($idx === false || $idx >= 3) return null; // offer is last movable forward stage
        return self::STAGES[$idx + 1];
    }

    // ── Relations ─────────────────────────────────────────────────────────
    public function jobPosting() { return $this->belongsTo(HrJobPosting::class, 'job_posting_id'); }
    public function interviews() { return $this->hasMany(HrInterview::class, 'applicant_id'); }
    public function employee()   { return $this->belongsTo(HrEmployee::class); }

    // ── Accessors ─────────────────────────────────────────────────────────
    public function getStageBadgeClass(): string
    {
        return match($this->stage) {
            'applied'   => 'bg-blue-100 text-blue-700',
            'screening' => 'bg-purple-100 text-purple-700',
            'interview' => 'bg-yellow-100 text-yellow-700',
            'offer'     => 'bg-orange-100 text-orange-700',
            'hired'     => 'bg-green-100 text-green-700',
            'rejected'  => 'bg-red-100 text-red-500',
            default     => 'bg-gray-100 text-gray-500',
        };
    }

    public function getInitials(): string
    {
        $parts = explode(' ', trim($this->name));
        return strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
    }
}
