<?php

namespace App\Models\HR;

use App\Traits\HR\HrAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrJobPosting extends Model
{
    use HrAuditable, SoftDeletes;

    protected $table = 'hr_job_postings';

    protected $fillable = [
        'department_id', 'position_id', 'title', 'description', 'requirements',
        'location', 'type', 'openings', 'hired_count', 'status',
        'created_by', 'posted_at', 'closes_at',
    ];

    protected $casts = [
        'posted_at'  => 'datetime',
        'closes_at'  => 'date',
        'openings'   => 'integer',
        'hired_count'=> 'integer',
    ];

    // ── Relations ─────────────────────────────────────────────────────────
    public function department() { return $this->belongsTo(HrDepartment::class); }
    public function position()   { return $this->belongsTo(HrPosition::class); }
    public function creator()    { return $this->belongsTo(HrStaff::class, 'created_by'); }
    public function applicants() { return $this->hasMany(HrApplicant::class, 'job_posting_id'); }

    // ── Computed ──────────────────────────────────────────────────────────
    public function getSpotsLeftAttribute(): int
    {
        return max(0, $this->openings - $this->hired_count);
    }

    public function getTypeLabel(): string
    {
        return match($this->type) {
            'full_time' => 'Full-time',
            'part_time' => 'Part-time',
            'contract'  => 'Contract',
            default     => ucfirst($this->type),
        };
    }

    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            'open'   => 'bg-green-100 text-green-700',
            'paused' => 'bg-yellow-100 text-yellow-700',
            'closed' => 'bg-gray-100 text-gray-500',
            default  => 'bg-blue-100 text-blue-700',
        };
    }
}
