<?php
namespace App\Services;
use App\Models\Deliveryman;
use Illuminate\Support\Facades\DB;

class ChallengeService {
    public static function onDeliveryComplete(Deliveryman $dm): void {
        $now = now();
        $challenges = DB::table('driver_challenges')->where('is_active', true)->where('type','delivery_count')
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at','<=',$now))
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at','>=',$now))->get();
        foreach ($challenges as $ch) {
            $existing = DB::table('driver_challenge_progress')
                ->where('deliveryman_id',$dm->id)->where('challenge_id',$ch->id)->first();
            if ($existing?->completed_at !== null) continue;
            $newCount = ($existing->current_count ?? 0) + 1;
            DB::table('driver_challenge_progress')->updateOrInsert(
                ['deliveryman_id'=>$dm->id,'challenge_id'=>$ch->id],
                ['current_count'=>$newCount,'updated_at'=>now(),'created_at'=>now()]
            );
            if ($newCount >= $ch->target_count) {
                DB::table('driver_challenge_progress')
                    ->where('deliveryman_id',$dm->id)->where('challenge_id',$ch->id)
                    ->update(['completed_at'=>now()]);
                $reward = (float)$ch->reward_amount;
                if ($reward > 0) {
                    $wallet = \App\Models\Wallet::getOrCreateFor('App\\Models\\User', $dm->user_id);
                    $wallet->credit($reward, "Challenge: {$ch->title}", 'App\\Models\\Deliveryman', $dm->id);
                    DB::table('driver_challenge_progress')
                        ->where('deliveryman_id',$dm->id)->where('challenge_id',$ch->id)
                        ->update(['reward_paid_at'=>now()]);
                    try {
                        $token = $dm->fcm_token ?? $dm->user?->fcm_token;
                        if ($token) FcmService::sendToToken($token,'Challenge Complete!',
                            "You completed '{$ch->title}' and earned \${$reward}!");
                    } catch (\Throwable) {}
                }
            }
        }
    }
}
