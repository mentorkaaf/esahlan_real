<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CommunityPost extends Model {
    use SoftDeletes;
    protected $fillable = ['user_id','group_id','page_id','shared_post_id','type','content','location','feeling','privacy','is_pinned','comments_disabled','views_count','likes_count','comments_count','shares_count','saves_count','poll_options','published_at','video_ready','moderation_status','moderation_score'];
    protected $casts = ['poll_options'=>'array','is_pinned'=>'boolean','comments_disabled'=>'boolean','video_ready'=>'boolean','published_at'=>'datetime'];

    /**
     * When a post is hard-deleted (forceDelete), remove all its media files
     * from storage. Soft deletes (->delete()) do NOT trigger this — files are
     * only cleaned up on permanent deletion so that restoring a soft-deleted
     * post still has its media intact.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::forceDeleting(function (CommunityPost $post) {
            try {
                $post->loadMissing('media');
                foreach ($post->media as $m) {
                    static::cleanMediaFiles($m);
                }
            } catch (\Throwable $e) {
                Log::warning("CommunityPost forceDeleting: storage cleanup failed for post {$post->id} — " . $e->getMessage());
            }
        });
    }

    /**
     * Delete all storage files for a single CommunityPostMedia row.
     * Video posts: delete the entire folder (HLS segments + optimized.mp4 + thumb).
     * Image posts: delete individual files.
     */
    private static function cleanMediaFiles(CommunityPostMedia $m): void
    {
        $hlsUrl = $m->getRawOriginal('hls_url');

        if ($hlsUrl) {
            // Video: resolve path and delete entire parent folder
            $path = static::resolveStoragePath($hlsUrl);
            if ($path) {
                // community/posts/{hash}/hls/master.m3u8 → community/posts/{hash}
                $folder = dirname(dirname($path));
                if (str_starts_with($folder, 'community/posts/') && $folder !== 'community/posts') {
                    if (Storage::disk('public')->exists($folder)) {
                        Storage::disk('public')->deleteDirectory($folder);
                        Log::info("CommunityPost: deleted video folder {$folder}");
                    }
                }
            }
        } else {
            // Image/audio: delete individual files
            foreach (['url', 'thumbnail'] as $field) {
                $val = $m->getRawOriginal($field);
                if (!$val) continue;
                $path = static::resolveStoragePath($val);
                if ($path && Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        }
    }

    /**
     * Resolve a stored media value to a relative storage path.
     * Handles both raw paths and proxy URLs (/api/v1/media?f=...).
     */
    private static function resolveStoragePath(?string $value): ?string
    {
        if (!$value) return null;
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            $query = parse_url($value, PHP_URL_QUERY);
            if (!$query) return null;
            parse_str($query, $params);
            return $params['f'] ?? null;
        }
        return $value;
    }

    public function user() { return $this->belongsTo(User::class); }
    public function group() { return $this->belongsTo(CommunityGroup::class, 'group_id'); }
    public function page() { return $this->belongsTo(CommunityBusinessPage::class, 'page_id'); }
    public function media() { return $this->hasMany(CommunityPostMedia::class, 'post_id')->orderBy('sort_order'); }
    public function reactions() { return $this->hasMany(CommunityPostReaction::class, 'post_id'); }
    public function comments() { return $this->hasMany(CommunityComment::class, 'post_id')->whereNull('parent_id')->latest(); }
    public function sharedPost() { return $this->belongsTo(CommunityPost::class, 'shared_post_id'); }
    public function hashtags() { return $this->belongsToMany(CommunityHashtag::class, 'community_post_hashtags', 'post_id', 'hashtag_id'); }
    public function saves() { return $this->hasMany(CommunitySavedPost::class, 'post_id'); }
    public function reports() { return $this->morphMany(CommunityReport::class, 'reportable'); }

    public function userReaction() {
        return $this->hasOne(CommunityPostReaction::class, 'post_id')->where('user_id', auth()->id());
    }
    public function isSavedByUser() {
        return CommunitySavedPost::where('user_id', auth()->id())->where('post_id', $this->id)->exists();
    }
}