<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinPurchase extends Model
{
    protected $fillable = [
        'user_id', 'package_id', 'coins_received', 'amount_paid',
        'currency', 'payment_method', 'payment_reference', 'status', 'payment_metadata',
    ];

    protected $casts = [
        'payment_metadata' => 'array',
        'amount_paid'      => 'float',
    ];

    public function user()    { return $this->belongsTo(User::class); }
    public function package() { return $this->belongsTo(CoinPackage::class); }
}
