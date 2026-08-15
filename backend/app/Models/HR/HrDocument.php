<?php

namespace App\Models\HR;

use App\Traits\HR\HrAuditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrDocument extends Model
{
    use HasFactory, SoftDeletes, HrAuditable;

    protected $table = 'hr_documents';

    protected $fillable = [
        'employee_id', 'type', 'title', 'file_path', 'expires_at', 'notes',
    ];

    protected $casts = ['expires_at' => 'date'];

    public function employee()
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'id'          => 'National ID',
            'contract'    => 'Contract',
            'certificate' => 'Certificate',
            'cv'          => 'CV / Resume',
            'photo'       => 'Photo',
            'other'       => 'Other',
            default       => ucfirst($this->type),
        };
    }
}
