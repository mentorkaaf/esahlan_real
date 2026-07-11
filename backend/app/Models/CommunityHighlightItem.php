<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityHighlightItem extends Model
{
    protected $fillable = ['highlight_id', 'content_type', 'content_id', 'sort_order'];

    public function highlight(): BelongsTo
    {
        return $this->belongsTo(CommunityHighlight::class);
    }
}
