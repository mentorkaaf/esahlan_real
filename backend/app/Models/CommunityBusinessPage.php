<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CommunityBusinessPage extends Model
{
    protected $guarded = ['id'];

    public function user()    { return $this->belongsTo(User::class); }
    public function ads()     { return $this->hasMany(CommunityAd::class, 'page_id'); }
    public function followers(){ return $this->belongsToMany(User::class, 'community_page_followers', 'page_id', 'user_id'); }
}
