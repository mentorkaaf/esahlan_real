<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeCoin extends Model
{
    protected $table = 'exchange_coins';
    protected $fillable = [
        'symbol','name','coingecko_id','logo_url','decimals',
        'deposit_enabled','withdrawal_enabled','buy_enabled','sell_enabled','p2p_enabled','is_active',
        'min_deposit','min_withdrawal','max_withdrawal','withdrawal_fee',
        'buy_fee_pct','sell_fee_pct','display_order',
    ];
    protected $casts = [
        'decimals'=>'integer','deposit_enabled'=>'boolean','withdrawal_enabled'=>'boolean',
        'buy_enabled'=>'boolean','sell_enabled'=>'boolean','p2p_enabled'=>'boolean','is_active'=>'boolean',
        'min_deposit'=>'float','min_withdrawal'=>'float','max_withdrawal'=>'float','withdrawal_fee'=>'float',
        'buy_fee_pct'=>'float','sell_fee_pct'=>'float',
    ];

    public function networks()   { return $this->hasMany(ExchangeNetwork::class, 'coin_id'); }
    public function price()      { return $this->hasOne(CryptoPrice::class, 'coin_id'); }
    public function p2pAds()     { return $this->hasMany(P2pAd::class, 'coin_id'); }
}
