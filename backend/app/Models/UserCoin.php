<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserCoin extends Model
{
    protected $fillable = ['user_id', 'balance'];

    public function user() { return $this->belongsTo(User::class); }

    // Deduct coins — returns false if insufficient
    public static function deduct(int $userId, int $amount): bool
    {
        $wallet = static::firstOrCreate(['user_id' => $userId], ['balance' => 0]);
        if ($wallet->balance < $amount) return false;
        $wallet->decrement('balance', $amount);
        return true;
    }

    public static function credit(int $userId, int $amount): void
    {
        static::firstOrCreate(['user_id' => $userId], ['balance' => 0])
            ->increment('balance', $amount);
    }
}
