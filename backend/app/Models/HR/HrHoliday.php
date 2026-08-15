<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;

class HrHoliday extends Model
{
    protected $table = 'hr_holidays';

    protected $fillable = ['name', 'date', 'is_recurring', 'notes'];

    protected $casts = [
        'date'         => 'date',
        'is_recurring' => 'boolean',
    ];

    /** Returns array of holiday date strings (Y-m-d) for a given year */
    public static function datesForYear(int $year): array
    {
        return static::whereYear('date', $year)
            ->orWhere('is_recurring', true)
            ->get()
            ->map(function ($h) use ($year) {
                if ($h->is_recurring) {
                    return $year . '-' . $h->date->format('m-d');
                }
                return $h->date->format('Y-m-d');
            })
            ->unique()
            ->values()
            ->toArray();
    }
}
