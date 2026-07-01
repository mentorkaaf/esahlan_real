<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CommunityPostMedia extends Model {
    protected $fillable = ['post_id','type','url','thumbnail','hls_url','duration','width','height','sort_order','transcoding_status','transcoding_progress'];
    public function post() { return $this->belongsTo(CommunityPost::class, 'post_id'); }

    public function getUrlAttribute(): ?string       { return cdn_url($this->attributes['url'] ?? null); }
    public function getThumbnailAttribute(): ?string { return cdn_url($this->attributes['thumbnail'] ?? null); }
    public function getHlsUrlAttribute(): ?string    { return cdn_url($this->attributes['hls_url'] ?? null); }
}
