<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityHighlight extends Model
{
    protected $fillable = ['user_id', 'title', 'cover_image', 'sort_order'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
