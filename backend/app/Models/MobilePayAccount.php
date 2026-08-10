<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobilePayAccount extends Model
{
    protected $fillable = [
        'name', 'account_number', 'ussd_template',
        'instructions', 'icon', 'logo', 'is_active', 'sort_order',
    ];

    /** Full public URL of the logo, or null if none uploaded. */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? url('storage/' . $this->logo) : null;
    }

    protected $casts = ['is_active' => 'boolean'];

    public function buildUssd(float $amount): string
    {
        return str_replace('{amount}', (string) $amount, $this->ussd_template);
    }
}
