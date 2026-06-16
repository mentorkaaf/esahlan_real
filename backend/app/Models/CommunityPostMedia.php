<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CommunityPostMedia extends Model {
    protected $fillable = ['post_id','type','url','thumbnail','duration','width','height','sort_order'];
    public function post() { return $this->belongsTo(CommunityPost::class, 'post_id'); }

    // Route media through the CORS-safe proxy so it loads on Flutter Web.
    public function getUrlAttribute(): ?string       { return cdn_url($this->attributes['url'] ?? null); }
    public function getThumbnailAttribute(): ?string { return cdn_url($this->attributes['thumbnail'] ?? null); }
}