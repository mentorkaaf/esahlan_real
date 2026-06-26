<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityComment extends Model {
    use SoftDeletes;
    protected $fillable = ['post_id','user_id','parent_id','content','media_url','media_type','likes_count','replies_count','is_pinned'];
    protected $casts = ['is_pinned'=>'boolean'];

    public function user() { return $this->belongsTo(User::class); }
    public function post() { return $this->belongsTo(CommunityPost::class, 'post_id'); }
    public function replies() { return $this->hasMany(CommunityComment::class, 'parent_id')->latest(); }
    public function parent() { return $this->belongsTo(CommunityComment::class, 'parent_id'); }
    public function reactions() { return $this->hasMany(CommunityCommentReaction::class, 'comment_id'); }
}