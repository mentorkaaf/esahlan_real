<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CommunityGroup extends Model {
    protected $fillable = ['owner_id','name','slug','description','cover_photo','avatar','privacy','category','location','approval_required','members_count','posts_count'];
    protected $casts = ['approval_required'=>'boolean'];

    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function members() { return $this->hasMany(CommunityGroupMember::class, 'group_id'); }
    public function posts() { return $this->hasMany(CommunityPost::class, 'group_id'); }
    public function membership() {
        return $this->hasOne(CommunityGroupMember::class, 'group_id')->where('user_id', auth()->id());
    }
}