<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CommunityPostMedia extends Model {
    protected $fillable = ['post_id','type','url','thumbnail','duration','width','height','sort_order'];
    public function post() { return $this->belongsTo(CommunityPost::class, 'post_id'); }
}