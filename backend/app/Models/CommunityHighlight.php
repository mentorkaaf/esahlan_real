<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityHighlight extends Model
{
    protected $fillable = ['user_id', 'title', 'cover_image', 'sort_order'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CommunityHighlightItem::class, 'highlight_id')->orderBy('sort_order');
    }
}
