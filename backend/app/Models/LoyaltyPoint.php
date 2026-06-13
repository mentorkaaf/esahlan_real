<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LoyaltyPoint extends Model {
    public $timestamps = false;
    protected $fillable = ['user_id','points','type','reference_type','reference_id','note','expires_at'];
    protected $casts = ['expires_at'=>'datetime'];
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;
    public function user() { return $this->belongsTo(User::class); }
}
