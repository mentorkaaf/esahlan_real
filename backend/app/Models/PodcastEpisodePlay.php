<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PodcastEpisodePlay extends Model {
    protected $fillable = ['user_id','episode_id','position','completed','play_count','last_played_at'];
    protected $casts = ['completed'=>'boolean','last_played_at'=>'datetime','position'=>'integer'];

    public function user()    { return $this->belongsTo(User::class); }
    public function episode() { return $this->belongsTo(PodcastEpisode::class, 'episode_id'); }
}
