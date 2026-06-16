<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CommunityStory extends Model {
    protected $fillable = ['user_id','type','media_url','thumbnail','text_content','bg_color','location','views_count','expires_at'];
    protected $casts = ['expires_at'=>'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function views() { return $this->hasMany(CommunityStoryView::class, 'story_id'); }

    // CORS-safe media URLs for Flutter Web.
    public function getMediaUrlAttribute(): ?string { return cdn_url($this->attributes['media_url'] ?? null); }
    public function getThumbnailAttribute(): ?string { return cdn_url($this->attributes['thumbnail'] ?? null); }

    public function isExpired(): bool { return $this->expires_at->isPast(); }
    public function isViewedBy(int $userId): bool {
        return $this->views()->where('user_id', $userId)->exists();
    }
}