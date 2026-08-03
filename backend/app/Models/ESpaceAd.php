<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ESpaceAd extends Model
{
    use HasFactory;

    protected $fillable = [
        'module', 'title', 'subtitle', 'description', 'image_url',
        'cta_text', 'deep_link', 'placement', 'priority', 'is_active',
        'starts_at', 'ends_at', 'impressions_count', 'clicks_count',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'starts_at'  => 'datetime',
        'ends_at'    => 'datetime',
        'priority'   => 'integer',
    ];

    // Modules supported
    public const MODULES = [
        'efood'     => ['label' => 'eFood',     'color' => '#FF6B35', 'deep_link' => '/efood'],
        'egrocery'  => ['label' => 'eGrocery',  'color' => '#22C55E', 'deep_link' => '/egrocery'],
        'eshop'     => ['label' => 'eShop',     'color' => '#8B5CF6', 'deep_link' => '/eshop'],
        'eparcel'   => ['label' => 'eParcel',   'color' => '#F59E0B', 'deep_link' => '/eparcel'],
        'emoving'   => ['label' => 'eMoving',   'color' => '#3B82F6', 'deep_link' => '/emoving'],
        'elearning' => ['label' => 'eLearning', 'color' => '#06B6D4', 'deep_link' => '/elearning'],
        'eexchange' => ['label' => 'eExchange', 'color' => '#EC4899', 'deep_link' => '/eexchange'],
        'erent'     => ['label' => 'eRent',     'color' => '#14B8A6', 'deep_link' => '/erent'],
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function scopeForPlacement($query, string $placement)
    {
        return $query->whereRaw("FIND_IN_SET(?, placement)", [$placement]);
    }

    public function getCtrAttribute(): float
    {
        if ($this->impressions_count === 0) return 0;
        return round($this->clicks_count / $this->impressions_count * 100, 2);
    }

    public function getModuleColorAttribute(): string
    {
        return self::MODULES[$this->module]['color'] ?? '#FF8A00';
    }

    public function getModuleLabelAttribute(): string
    {
        return self::MODULES[$this->module]['label'] ?? ucfirst($this->module);
    }
}
