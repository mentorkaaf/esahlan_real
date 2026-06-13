<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class Conversation extends Model {
    protected $fillable = ['uuid','type','order_id'];
    protected static function boot() {
        parent::boot();
        static::creating(function ($c) { $c->uuid = (string)Str::uuid(); });
    }
    public function order() { return $this->belongsTo(Order::class); }
    public function messages() { return $this->hasMany(Message::class); }
    public function latestMessage() { return $this->hasOne(Message::class)->latestOfMany(); }
}
