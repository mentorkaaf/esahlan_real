<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CryptoPrice extends Model
{
    protected $table = 'crypto_prices';
    protected $fillable = ['coin_id','price_usd','change_24h','change_7d','volume_24h','market_cap','high_24h','low_24h','spread_pct','manual_price','use_manual','manual_override','override_price','override_until','fetched_at'];
    protected $casts = ['price_usd'=>'float','change_24h'=>'float','change_7d'=>'float','volume_24h'=>'float','market_cap'=>'float','high_24h'=>'float','low_24h'=>'float','spread_pct'=>'float','manual_price'=>'float','use_manual'=>'boolean','fetched_at'=>'datetime'];
    public function coin() { return $this->belongsTo(ExchangeCoin::class,'coin_id'); }

    public function effectivePrice(): float
    {
        if ($this->use_manual && $this->manual_price > 0) return (float) $this->manual_price;
        return (float) $this->price_usd * (1 + $this->spread_pct / 100);
    }
}
