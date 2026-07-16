<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Podcast extends Model {
    use SoftDeletes;

    protected $fillable = [
        'user_id','category_id','title','slug','description','cover_image','language',
        'website_url','rss_url','total_episodes','total_plays','total_followers',
        'rating','rating_count','is_verified','is_featured','privacy','status','published_at',
    ];
    protected $casts = [
        'is_verified'=>'boolean','is_featured'=>'boolean',
        'published_at'=>'datetime','rating'=>'float',
        'total_episodes'=>'integer','total_plays'=>'integer','total_followers'=>'integer',
    ];

    public function user()     { return $this->belongsTo(User::class); }
    public function category() { return $this->belongsTo(PodcastCategory::class, 'category_id'); }
    public function episodes() { return $this->hasMany(PodcastEpisode::class)->where('status','published')->orderByDesc('published_at'); }
    public function allEpisodes() { return $this->hasMany(PodcastEpisode::class)->orderByDesc('published_at'); }
    public function follows()  { return $this->hasMany(PodcastFollow::class); }

    public function isFollowedByUser(?int $userId): bool {
        if (!$userId) return false;
        return $this->follows()->where('user_id', $userId)->exists();
    }

    public function scopePublished($q) {
        return $q->where('status','active')->where('privacy','public');
    }
    public function scopeFeatured($q) {
        return $q->where('is_featured', true);
    }
}
