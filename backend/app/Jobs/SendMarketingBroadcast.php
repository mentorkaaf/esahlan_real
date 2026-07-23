<?php

namespace App\Jobs;

use App\Models\{MarketingBroadcast, MarketingBroadcastUser, User};
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendMarketingBroadcast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 600;

    public function __construct(public readonly int $broadcastId) {}

    public function handle(): void
    {
        $broadcast = MarketingBroadcast::findOrFail($this->broadcastId);
        if ($broadcast->status !== 'sending') return;

        $sent = 0;
        $query = User::whereNotNull('fcm_token');

        if ($broadcast->target === 'active_30d') {
            $query->where('last_active_at', '>=', now()->subDays(30));
        } elseif ($broadcast->target === 'specific') {
            $ids = $broadcast->target_filters['user_ids'] ?? [];
            if (empty($ids)) {
                $broadcast->update(['status' => 'sent', 'sent_count' => 0, 'sent_at' => now()]);
                return;
            }
            $query->whereIn('id', $ids);
        }

        $query->chunkById(100, function ($users) use ($broadcast, &$sent) {
                foreach ($users as $user) {
                    try {
                        // Create read record
                        MarketingBroadcastUser::firstOrCreate(
                            ['broadcast_id' => $broadcast->id, 'user_id' => $user->id]
                        );

                        // FCM push
                        FcmService::sendToToken(
                            $user->fcm_token,
                            $broadcast->title,
                            substr($broadcast->body, 0, 200),
                            [
                                'type'           => 'marketing',
                                'broadcast_uuid' => $broadcast->uuid,
                                'deep_link'      => '/inbox/broadcast/' . $broadcast->uuid,
                                'module'         => $broadcast->module ?? '',
                                'cta_route'      => $broadcast->cta_route ?? '',
                                'image_url'      => $broadcast->image_url ?? '',
                            ],
                            $broadcast->image_url
                        );
                        $sent++;
                    } catch (\Throwable $e) {
                        Log::warning("[MarketingBroadcast] user#{$user->id}: " . $e->getMessage());
                    }
                }
            });

        $broadcast->update(['status' => 'sent', 'sent_count' => $sent, 'sent_at' => now()]);
        Log::info("[MarketingBroadcast] #{$this->broadcastId} sent to {$sent} users");
    }
}
