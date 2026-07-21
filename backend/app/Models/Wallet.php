<?php

namespace App\Models;

use App\Events\WalletTransactionOccurred;
use App\Services\FcmService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Wallet extends Model
{
    protected $fillable = [
        'owner_type','owner_id','balance','pending_balance',
        'total_earned','total_withdrawn','currency','is_active',
    ];

    protected $casts = [
        'balance'=>'float','pending_balance'=>'float',
        'total_earned'=>'float','total_withdrawn'=>'float','is_active'=>'boolean',
    ];

    public function transactions() { return $this->hasMany(Transaction::class); }
    public function withdrawalRequests() { return $this->hasMany(WithdrawalRequest::class); }

    public function credit(float $amount, string $note = '', ?string $refType = null, ?int $refId = null, string $method = 'wallet'): Transaction
    {
        $tx = DB::transaction(function () use ($amount, $note, $refType, $refId, $method) {
            $wallet = self::lockForUpdate()->find($this->id);
            $before = $wallet->balance;
            $after  = $before + $amount;
            $wallet->update(['balance' => $after, 'total_earned' => $wallet->total_earned + $amount]);
            return $wallet->transactions()->create([
                'uuid'           => (string) Str::uuid(),
                'type'           => 'credit',
                'amount'         => $amount,
                'balance_before' => $before,
                'balance_after'  => $after,
                'note'           => $note,
                'reference_type' => $refType,
                'reference_id'   => $refId,
                'payment_method' => $method,
                'status'         => 'completed',
            ]);
        });

        $this->_dispatchWalletEvent($tx, $note, $method);
        return $tx;
    }

    public function debit(float $amount, string $note = '', ?string $refType = null, ?int $refId = null, string $method = 'wallet'): Transaction
    {
        $tx = DB::transaction(function () use ($amount, $note, $refType, $refId, $method) {
            $wallet = self::lockForUpdate()->find($this->id);
            if ($wallet->balance < $amount) throw new \Exception('Insufficient wallet balance');
            $before = $wallet->balance;
            $after  = $before - $amount;
            $wallet->update(['balance' => $after, 'total_withdrawn' => $wallet->total_withdrawn + $amount]);
            return $wallet->transactions()->create([
                'uuid'           => (string) Str::uuid(),
                'type'           => 'debit',
                'amount'         => $amount,
                'balance_before' => $before,
                'balance_after'  => $after,
                'note'           => $note,
                'reference_type' => $refType,
                'reference_id'   => $refId,
                'payment_method' => $method,
                'status'         => 'completed',
            ]);
        });

        $this->_dispatchWalletEvent($tx, $note, $method);
        return $tx;
    }

    private function _dispatchWalletEvent(Transaction $tx, string $note, string $method): void
    {
        if ($this->owner_type !== 'App\\Models\\User') return;

        try {
            // Broadcast via Reverb WebSocket
            broadcast(new WalletTransactionOccurred(
                userId:    $this->owner_id,
                txType:    $tx->type,
                amount:    (float) $tx->amount,
                balance:   (float) $tx->balance_after,
                note:      $note,
                method:    $method,
                createdAt: (string) $tx->created_at,
            ));

            // FCM push notification
            $user = \App\Models\User::find($this->owner_id);
            if ($user?->fcm_token) {
                FcmService::sendWalletEvent($user->fcm_token, $tx->type, (float) $tx->amount, (float) $tx->balance_after, $note);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[Wallet] event dispatch failed: ' . $e->getMessage());
        }
    }

    public static function getOrCreateFor(string $ownerType, int $ownerId): self
    {
        return self::firstOrCreate(
            ['owner_type'=>$ownerType,'owner_id'=>$ownerId],
            ['balance'=>0,'currency'=>config('app.currency','USD')]
        );
    }
}
