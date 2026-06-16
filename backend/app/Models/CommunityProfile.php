<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CommunityProfile extends Model {
    protected $fillable = ['user_id','display_name','username','bio','cover_photo','website','is_verified','is_business','business_category','followers_count','following_count','posts_count','privacy'];
    protected $casts = ['is_verified'=>'boolean','is_business'=>'boolean'];

    public function user() { return $this->belongsTo(User::class); }
    public function posts() { return $this->hasMany(CommunityPost::class, 'user_id', 'user_id'); }
    public function followers() { return $this->hasMany(CommunityFollow::class, 'following_id', 'user_id'); }

    // CORS-safe media URL for Flutter Web.
    public function getCoverPhotoAttribute(): ?string { return cdn_url($this->attributes['cover_photo'] ?? null); }
}