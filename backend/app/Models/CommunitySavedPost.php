<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CommunitySavedPost extends Model {
    protected $fillable = ['user_id','post_id'];
    public function post() { return $this->belongsTo(CommunityPost::class, 'post_id'); }
}