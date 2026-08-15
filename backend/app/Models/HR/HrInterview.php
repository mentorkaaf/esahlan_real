<?php

namespace App\Models\HR;

use App\Traits\HR\HrAuditable;
use Illuminate\Database\Eloquent\Model;

class HrInterview extends Model
{
    use HrAuditable;

    protected $table = 'hr_interviews';

    protected $fillable = [
        'applicant_id', 'interviewer_id', 'created_by',
        'scheduled_at', 'mode', 'location_or_link',
        'status', 'feedback', 'result',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function applicant()   { return $this->belongsTo(HrApplicant::class); }
    public function interviewer() { return $this->belongsTo(HrStaff::class, 'interviewer_id'); }
    public function creator()     { return $this->belongsTo(HrStaff::class, 'created_by'); }

    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            'scheduled'  => 'bg-blue-100 text-blue-700',
            'completed'  => 'bg-green-100 text-green-700',
            'cancelled'  => 'bg-gray-100 text-gray-500',
            default      => 'bg-gray-100 text-gray-500',
        };
    }
}
