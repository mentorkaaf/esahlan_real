<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CommunityStoryView extends Model {
    protected $fillable = ['story_id','user_id','reaction'];
    public function user() { return $this->belongsTo(User::class); }
}