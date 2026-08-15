<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrLeaveType extends Model
{
    use HasFactory;
    protected $table = 'hr_leave_types';

    protected $fillable = [
        'name', 'code', 'days_per_year',
        'is_paid', 'requires_document', 'is_active', 'description',
    ];

    protected $casts = [
        'is_paid'            => 'boolean',
        'requires_document'  => 'boolean',
        'is_active'          => 'boolean',
        'days_per_year'      => 'integer',
    ];

    public function balances(): HasMany
    {
        return $this->hasMany(HrLeaveBalance::class, 'leave_type_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(HrLeaveRequest::class, 'leave_type_id');
    }
}
