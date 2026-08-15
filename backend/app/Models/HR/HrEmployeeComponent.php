<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrEmployeeComponent extends Model
{
    protected $table = 'hr_employee_components';

    protected $fillable = [
        'employee_id', 'component_id', 'override_value',
        'effective_from', 'effective_to',
    ];

    protected $casts = [
        'effective_from'  => 'date',
        'effective_to'    => 'date',
        'override_value'  => 'decimal:4',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(HrSalaryComponent::class, 'component_id');
    }

    /** Effective value: override or component default */
    public function effectiveValue(): float
    {
        return $this->override_value !== null
            ? (float) $this->override_value
            : (float) $this->component->value;
    }
}
