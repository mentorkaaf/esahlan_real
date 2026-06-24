<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CommunityAdInteraction extends Model
{
    protected $guarded = ['id'];

    public function ad()   { return $this->belongsTo(CommunityAd::class, 'ad_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
