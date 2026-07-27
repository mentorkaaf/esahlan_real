<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbandonedCartItem extends Model
{
    protected $table = 'abandoned_cart_items';

    protected $fillable = [
        'user_id', 'module', 'product_id', 'product_name',
        'product_image', 'price', 'quantity', 'meta',
        'added_at', 'notified_30min_at', 'notified_2h_at', 'notified_24h_at',
    ];

    protected $casts = [
        'meta'               => 'array',
        'price'              => 'float',
        'added_at'           => 'datetime',
        'notified_30min_at'  => 'datetime',
        'notified_2h_at'     => 'datetime',
        'notified_24h_at'    => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
}
