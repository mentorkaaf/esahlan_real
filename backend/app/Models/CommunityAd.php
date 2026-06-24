<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CommunityAd extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['target_interests' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function user()    { return $this->belongsTo(User::class); }
    public function page()    { return $this->belongsTo(CommunityBusinessPage::class, 'page_id'); }
    public function pricing() { return $this->belongsTo(CommunityAdPricing::class, 'pricing_id'); }
    public function interactions() { return $this->hasMany(CommunityAdInteraction::class, 'ad_id'); }
}
