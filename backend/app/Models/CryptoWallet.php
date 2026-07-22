<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\CryptoAddressService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CryptoWallet extends Model
{
    protected $table = 'crypto_wallets';
    protected $fillable = ['user_id','coin_id','network_id','address','balance','pending_balance','locked_balance','total_deposited','total_withdrawn'];
    protected $casts = ['balance'=>'float','pending_balance'=>'float','locked_balance'=>'float','total_deposited'=>'float','total_withdrawn'=>'float'];

    public function user()    { return $this->belongsTo(User::class); }
    public function coin()    { return $this->belongsTo(ExchangeCoin::class,'coin_id'); }
    public function network() { return $this->belongsTo(ExchangeNetwork::class,'network_id'); }

    public static function getOrCreate(int $userId, int $coinId, int $networkId): self
    {
        return self::firstOrCreate(
            ['user_id'=>$userId,'coin_id'=>$coinId,'network_id'=>$networkId],
            ['address'=>self::generateAddress($coinId, $userId, $networkId),'balance'=>0]
        );
    }

    public static function generateAddress(int $coinId, int $userId = 0, ?int $networkId = null): string
    {
        $coin    = ExchangeCoin::find($coinId);
        $network = $networkId ? \App\Models\ExchangeNetwork::find($networkId) : null;
        $chain   = strtoupper($network?->chain ?? '');
        $symbol  = strtoupper($coin?->symbol ?? '');

        // Use real secp256k1 derivation if GMP is available and user ID is provided
        if ($userId > 0 && extension_loaded('gmp')) {
            try {
                if (str_contains($chain, 'TRC') || $chain === 'TRON' || $symbol === 'TRX') {
                    return CryptoAddressService::getTronAddress($userId);
                }
                if (str_contains($chain, 'ERC') || $symbol === 'ETH') {
                    return CryptoAddressService::getEthAddress($userId);
                }
                if (str_contains($chain, 'BEP') || $symbol === 'BNB') {
                    return CryptoAddressService::getBscAddress($userId);
                }
                // Default: use Tron-style for USDT (most common)
                return CryptoAddressService::getTronAddress($userId);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[CryptoWallet] Address derivation failed: ' . $e->getMessage());
            }
        }

        // Fallback (should not happen in production with GMP)
        return 'T' . strtoupper(bin2hex(random_bytes(20)));
    }

    public function credit(float $amount, string $type, string $note = '', ?string $refType = null, ?int $refId = null): CryptoTransaction
    {
        return DB::transaction(function () use ($amount, $type, $note, $refType, $refId) {
            $wallet = self::lockForUpdate()->find($this->id);
            $before = $wallet->balance;
            $after  = $before + $amount;
            $wallet->update(['balance'=>$after, 'total_deposited'=>$wallet->total_deposited + $amount]);
            return $this->_log($type, $amount, 0, $before, $after, $note, $refType, $refId);
        });
    }

    public function debit(float $amount, string $type, string $note = '', float $fee = 0, ?string $refType = null, ?int $refId = null): CryptoTransaction
    {
        return DB::transaction(function () use ($amount, $type, $note, $fee, $refType, $refId) {
            $wallet = self::lockForUpdate()->find($this->id);
            $total  = $amount + $fee;
            if ($wallet->balance < $total) throw new \Exception("Insufficient {$wallet->coin->symbol} balance");
            $before = $wallet->balance;
            $after  = $before - $total;
            $wallet->update(['balance'=>$after,'total_withdrawn'=>$wallet->total_withdrawn + $total]);
            return $this->_log($type, $amount, $fee, $before, $after, $note, $refType, $refId);
        });
    }

    public function lock(float $amount): void
    {
        self::where('id',$this->id)->increment('locked_balance', $amount);
        self::where('id',$this->id)->decrement('balance', $amount);
        $this->refresh();
    }

    public function unlock(float $amount, bool $credit = true): void
    {
        self::where('id',$this->id)->decrement('locked_balance', $amount);
        if ($credit) self::where('id',$this->id)->increment('balance', $amount);
        $this->refresh();
    }

    private function _log(string $type, float $amount, float $fee, float $before, float $after, string $note, ?string $refType, ?int $refId): CryptoTransaction
    {
        return CryptoTransaction::create([
            'uuid'=>(string)Str::uuid(),'user_id'=>$this->user_id,'coin_id'=>$this->coin_id,
            'wallet_id'=>$this->id,'type'=>$type,'amount'=>$amount,'fee'=>$fee,
            'balance_before'=>$before,'balance_after'=>$after,'note'=>$note,
            'reference_type'=>$refType,'reference_id'=>$refId,'status'=>'completed',
        ]);
    }
}
