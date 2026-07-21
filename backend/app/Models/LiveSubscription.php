<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveSubscription extends Model
{
    protected $fillable = ['subscriber_id', 'host_id', 'tier', 'price_usd', 'expires_at'];
    protected $casts    = ['expires_at' => 'datetime'];

    public function subscriber() { return $this->belongsTo(User::class, 'subscriber_id'); }
    public function host()       { return $this->belongsTo(User::class, 'host_id'); }

    public function isActive(): bool
    {
        return $this->expires_at && $this->expires_at->isFuture();
    }
}
