<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CommunityChat extends Model {
    protected $fillable = ['type','name','avatar'];

    public function members() { return $this->hasMany(CommunityChatMember::class, 'chat_id'); }
    public function messages() { return $this->hasMany(CommunityMessage::class, 'chat_id')->latest(); }
    public function lastMessage() { return $this->hasOne(CommunityMessage::class, 'chat_id')->latest(); }
    public function membership() {
        return $this->hasOne(CommunityChatMember::class, 'chat_id')->where('user_id', auth()->id());
    }
    public function unreadCount(int $userId): int {
        $member = $this->members()->where('user_id', $userId)->first();
        if (!$member) return 0;
        return $this->messages()->when($member->last_read_at, fn($q) => $q->where('created_at','>',$member->last_read_at))->count();
    }

    // CORS-safe group-chat avatar for Flutter Web.
    public function getAvatarAttribute(): ?string { return cdn_url($this->attributes['avatar'] ?? null); }
}