<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ExchangeNetwork extends Model
{
    protected $table = 'exchange_networks';
    protected $fillable = ['coin_id','name','chain','contract_address','confirmations_required','withdrawal_fee','deposit_enabled','withdrawal_enabled','is_maintenance','is_active'];
    protected $casts = ['deposit_enabled'=>'boolean','withdrawal_enabled'=>'boolean','is_maintenance'=>'boolean','is_active'=>'boolean','withdrawal_fee'=>'float'];
    public function coin() { return $this->belongsTo(ExchangeCoin::class, 'coin_id'); }
}
