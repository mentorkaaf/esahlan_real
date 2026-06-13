<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CommunityHashtag extends Model {
    protected $fillable = ['name','posts_count'];
    public function posts() { return $this->belongsToMany(CommunityPost::class, 'community_post_hashtags', 'hashtag_id', 'post_id'); }
}