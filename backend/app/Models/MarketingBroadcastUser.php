<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingBroadcastUser extends Model
{
    protected $fillable = ['broadcast_id', 'user_id', 'is_read', 'cta_clicked', 'read_at', 'clicked_at'];
    protected $casts    = ['is_read' => 'boolean', 'cta_clicked' => 'boolean', 'read_at' => 'datetime', 'clicked_at' => 'datetime'];
}
