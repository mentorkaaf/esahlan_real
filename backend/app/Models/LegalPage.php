<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalPage extends Model
{
    protected $fillable = [
        'slug', 'title_en', 'title_so',
        'content_en', 'content_so', 'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->where('is_published', true)->first();
    }
}
