<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinPackage extends Model
{
    protected $fillable = [
        'name', 'coins', 'bonus_coins', 'price', 'currency',
        'badge_label', 'is_featured', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_active'   => 'boolean',
        'price'       => 'float',
    ];

    public function totalCoins(): int
    {
        return $this->coins + $this->bonus_coins;
    }
}
