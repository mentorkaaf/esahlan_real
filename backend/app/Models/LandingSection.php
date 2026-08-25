<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingSection extends Model
{
    protected $fillable = [
        'slug', 'parent_slug', 'label', 'icon', 'type', 'is_enabled', 'sort_order',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function children()
    {
        return $this->hasMany(self::class, 'parent_slug', 'slug')
                    ->orderBy('sort_order');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_slug', 'slug');
    }

    /** Returns the full settings tree (top-level + nested children), cached. */
    public static function tree(): array
    {
        return cache()->remember('landing:sections:tree', 60, function () {
            $all = self::orderBy('sort_order')->get()->groupBy('parent_slug');

            $top = $all->get('') ?? $all->get(null) ?? collect();

            return $top->map(function ($section) use ($all) {
                $children = $all->get($section->slug, collect());
                return [
                    'slug'       => $section->slug,
                    'label'      => $section->label,
                    'type'       => $section->type,
                    'is_enabled' => $section->is_enabled,
                    'children'   => $children->map(fn ($c) => [
                        'slug'       => $c->slug,
                        'label'      => $c->label,
                        'type'       => $c->type,
                        'is_enabled' => $c->is_enabled,
                    ])->values()->toArray(),
                ];
            })->values()->toArray();
        });
    }

    /** Flush the tree cache after any save. */
    public static function flushCache(): void
    {
        cache()->forget('landing:sections:tree');
    }
}
