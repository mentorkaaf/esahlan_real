<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PodcastFollow extends Model {
    protected $fillable = ['user_id','podcast_id','notify_new_episodes'];
    protected $casts = ['notify_new_episodes'=>'boolean'];

    public function user()    { return $this->belongsTo(User::class); }
    public function podcast() { return $this->belongsTo(Podcast::class); }
}
