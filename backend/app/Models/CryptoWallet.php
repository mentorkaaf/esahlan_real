<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
            ['address'=>self::generateAddress($coinId),'balance'=>0]
        );
    }

    public static function generateAddress(int $coinId): string
    {
        $coin  = ExchangeCoin::find($coinId);
        $hex20 = bin2hex(random_bytes(20));
        $hex32 = bin2hex(random_bytes(32));
        return match($coin?->symbol) {
            'BTC'       => '1' . self::hexToBase58($hex20),
            'ETH','BNB' => '0x' . strtoupper($hex20),
            'SOL'       => self::hexToBase58($hex32),
            'XRP'       => 'r' . self::hexToBase58($hex20),
            default     => 'T' . self::hexToBase58($hex20),
        };
    }

    private static function hexToBase58(string $hex): string
    {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        // Convert hex → decimal string using bcmath (always available in Laravel)
        $decimal = '0';
        for ($i = 0; $i < strlen($hex); $i++) {
            $decimal = bcadd(bcmul($decimal, '16'), (string) hexdec($hex[$i]));
        }
        $result = '';
        while (bccomp($decimal, '0') > 0) {
            $rem     = (int) bcmod($decimal, '58');
            $result  = $alphabet[$rem] . $result;
            $decimal = bcdiv($decimal, '58', 0);
        }
        return $result ?: '1';
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
