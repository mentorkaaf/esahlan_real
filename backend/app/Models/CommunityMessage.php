<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityMessage extends Model {
    use SoftDeletes;
    protected $fillable = ['chat_id','user_id','reply_to_id','type','content','media_url','duration','is_deleted'];
    protected $casts = ['is_deleted'=>'boolean'];

    public function user() { return $this->belongsTo(User::class); }
    public function chat() { return $this->belongsTo(CommunityChat::class, 'chat_id'); }
    public function replyTo() { return $this->belongsTo(CommunityMessage::class, 'reply_to_id'); }

    // CORS-safe media URL for Flutter Web.
    public function getMediaUrlAttribute(): ?string { return cdn_url($this->attributes['media_url'] ?? null); }
}