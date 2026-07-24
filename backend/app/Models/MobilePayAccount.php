<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobilePayAccount extends Model
{
    protected $fillable = [
        'name', 'account_number', 'ussd_template',
        'instructions', 'icon', 'is_active', 'sort_order',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function buildUssd(float $amount): string
    {
        return str_replace('{amount}', (string) $amount, $this->ussd_template);
    }
}
