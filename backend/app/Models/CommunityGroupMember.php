<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CommunityGroupMember extends Model {
    protected $fillable = ['group_id','user_id','role','status'];
    public function user() { return $this->belongsTo(User::class); }
    public function group() { return $this->belongsTo(CommunityGroup::class, 'group_id'); }
}