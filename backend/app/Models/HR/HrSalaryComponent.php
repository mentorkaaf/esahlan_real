<?php

namespace App\Models\HR;

use App\Traits\HR\HrAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrSalaryComponent extends Model
{
    use HrAuditable;

    protected $table = 'hr_salary_components';

    protected $fillable = [
        'name', 'code', 'type', 'calculation', 'value',
        'is_taxable', 'is_active', 'description',
    ];

    protected $casts = [
        'is_taxable' => 'boolean',
        'is_active'  => 'boolean',
        'value'      => 'decimal:4',
    ];

    public function employeeComponents(): HasMany
    {
        return $this->hasMany(HrEmployeeComponent::class, 'component_id');
    }

    /** Compute the USD amount for this component given a base salary */
    public function computeAmount(float $baseSalary): float
    {
        if ($this->calculation === 'fixed') {
            return (float) $this->value;
        }
        // percentage
        return round($baseSalary * ((float) $this->value / 100), 2);
    }

    public function getTypeColorAttribute(): string
    {
        return $this->type === 'earning' ? 'green' : 'red';
    }

    public function getTypeLabelAttribute(): string
    {
        return ucfirst($this->type);
    }
}
