<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PodcastEpisode extends Model {
    use SoftDeletes;

    protected $fillable = [
        'podcast_id','user_id','title','slug','description','show_notes','audio_url','hls_url',
        'cover_image','duration','file_size','bitrate','play_count','like_count','comment_count',
        'download_count','share_count','season','episode_number','episode_type','is_explicit',
        'status','published_at','scheduled_at','allow_downloads','allow_comments',
        'chapters','transcript','ai_summary','tags',
    ];
    protected $casts = [
        'is_explicit'=>'boolean','allow_downloads'=>'boolean','allow_comments'=>'boolean',
        'published_at'=>'datetime','scheduled_at'=>'datetime',
        'chapters'=>'array','tags'=>'array',
        'duration'=>'integer','play_count'=>'integer','like_count'=>'integer',
    ];

    public function podcast()  { return $this->belongsTo(Podcast::class); }
    public function user()     { return $this->belongsTo(User::class); }
    public function likes()    { return $this->hasMany(PodcastLike::class, 'episode_id'); }
    public function saves()    { return $this->hasMany(PodcastSave::class, 'episode_id'); }
    public function plays()    { return $this->hasMany(PodcastEpisodePlay::class, 'episode_id'); }

    public function isLikedByUser(?int $userId): bool {
        if (!$userId) return false;
        return $this->likes()->where('user_id', $userId)->exists();
    }
    public function isSavedByUser(?int $userId): bool {
        if (!$userId) return false;
        return $this->saves()->where('user_id', $userId)->exists();
    }
    public function userPlay(?int $userId): ?PodcastEpisodePlay {
        if (!$userId) return null;
        return $this->plays()->where('user_id', $userId)->first();
    }

    public function getDurationFormattedAttribute(): string {
        $h = intdiv($this->duration, 3600);
        $m = intdiv($this->duration % 3600, 60);
        $s = $this->duration % 60;
        return $h > 0
            ? sprintf('%d:%02d:%02d', $h, $m, $s)
            : sprintf('%d:%02d', $m, $s);
    }

    public function scopePublished($q) {
        return $q->where('status','published')->where('published_at','<=',now());
    }
}
