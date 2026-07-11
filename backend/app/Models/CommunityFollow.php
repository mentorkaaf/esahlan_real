<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CommunityFollow extends Model {
    protected $fillable = ['follower_id','following_id','status'];

    protected static function booted(): void
    {
        // Invalidate the follower's feed cache immediately on follow/unfollow
        // so the new follow appears in the next feed request, not 5 minutes later.
        $bust = fn (self $m) => Cache::forget("user:{$m->follower_id}:following");
        static::created($bust);
        static::deleted($bust);
    }

    public function follower() { return $this->belongsTo(User::class, 'follower_id'); }
    public function following() { return $this->belongsTo(User::class, 'following_id'); }
}