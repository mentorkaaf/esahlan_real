<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gift extends Model
{
    protected $fillable = [
        'name', 'emoji', 'animation', 'animation_url', 'icon_url',
        'coins', 'host_coins', 'sort', 'display_priority', 'is_active', 'is_featured',
        'category', 'rarity', 'sound_effect', 'scheduled_start', 'scheduled_end',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'is_featured' => 'boolean',
        'scheduled_start' => 'datetime',
        'scheduled_end'   => 'datetime',
    ];

    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('scheduled_start')
                  ->orWhere('scheduled_start', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('scheduled_end')
                  ->orWhere('scheduled_end', '>=', now());
            });
    }

    public function transactions()
    {
        return $this->hasMany(GiftTransaction::class);
    }
}
