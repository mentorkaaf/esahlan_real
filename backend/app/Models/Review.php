<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Review extends Model {
    protected $fillable = ['user_id','order_id','reviewable_type','reviewable_id','rating','comment','images','is_approved','vendor_reply','vendor_replied_at'];
    protected $casts = ['images'=>'array','is_approved'=>'boolean','vendor_replied_at'=>'datetime','rating'=>'integer'];
    public function user() { return $this->belongsTo(User::class); }
    public function order() { return $this->belongsTo(Order::class); }
    public function reviewable() { return $this->morphTo(); }
}
