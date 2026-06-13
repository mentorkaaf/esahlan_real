<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CommunityCommentReaction extends Model {
    protected $fillable = ['comment_id','user_id','type'];
    public function user() { return $this->belongsTo(User::class); }
}