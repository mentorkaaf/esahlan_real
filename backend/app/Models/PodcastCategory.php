<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PodcastCategory extends Model {
    protected $fillable = ['name','slug','icon','color','description','cover_image','podcast_count','sort_order','is_active'];
    protected $casts = ['is_active'=>'boolean','podcast_count'=>'integer','sort_order'=>'integer'];

    public function podcasts() {
        return $this->hasMany(Podcast::class, 'category_id')->where('status','active')->where('privacy','public');
    }
}
