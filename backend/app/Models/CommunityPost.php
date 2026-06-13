<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityPost extends Model {
    use SoftDeletes;
    protected $fillable = ['user_id','group_id','shared_post_id','type','content','location','feeling','privacy','is_pinned','comments_disabled','views_count','likes_count','comments_count','shares_count','saves_count','poll_options','published_at'];
    protected $casts = ['poll_options'=>'array','is_pinned'=>'boolean','comments_disabled'=>'boolean','published_at'=>'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function group() { return $this->belongsTo(CommunityGroup::class, 'group_id'); }
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