<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingBroadcast extends Model
{
    protected $fillable = [
        'uuid', 'created_by', 'title', 'body', 'image_url', 'video_url',
        'module', 'cta_label', 'cta_route', 'target', 'target_filters',
        'status', 'sent_count', 'sent_at',
    ];

    protected $casts = [
        'target_filters' => 'array',
        'sent_at'        => 'datetime',
    ];

    public function reads() { return $this->hasMany(MarketingBroadcastUser::class, 'broadcast_id'); }

    public function readByUser(int $userId): ?MarketingBroadcastUser
    {
        return $this->reads()->where('user_id', $userId)->first();
    }
}
